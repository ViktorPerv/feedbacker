package handler

import (
	"context"
	"encoding/json"
	"errors"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"widget-server/internal/cache"
	"widget-server/internal/model"
	"widget-server/internal/store"
)

// ── Test double ──

type fakeStore struct {
	space     *model.Space
	widget    *model.Widget
	wall      *model.Wall
	spaceErr  error
	widgetErr error
	wallErr   error

	views  []model.WidgetView
	clicks []model.TestimonialClick

	pingErr error

	filterGot model.WallFilter
	slugGot   string
}

func (f *fakeStore) Space(context.Context, string) (*model.Space, error) {
	if f.spaceErr != nil {
		return nil, f.spaceErr
	}

	return f.space, nil
}

func (f *fakeStore) Widget(context.Context, string) (*model.Widget, error) {
	if f.widgetErr != nil {
		return nil, f.widgetErr
	}

	return f.widget, nil
}

func (f *fakeStore) Wall(_ context.Context, slug string, filter model.WallFilter) (*model.Wall, error) {
	f.slugGot = slug
	f.filterGot = filter

	if f.wallErr != nil {
		return nil, f.wallErr
	}

	return f.wall, nil
}

func (f *fakeStore) RecordView(_ context.Context, view model.WidgetView) error {
	f.views = append(f.views, view)
	return nil
}

func (f *fakeStore) RecordClick(_ context.Context, click model.TestimonialClick) error {
	f.clicks = append(f.clicks, click)
	return nil
}

func (f *fakeStore) Ping(context.Context) error { return f.pingErr }
func (f *fakeStore) Close()                     {}

// ── Fixtures ──

func activeSpace() *model.Space {
	return &model.Space{
		ID:              "11111111-1111-1111-1111-111111111111",
		Slug:            "acme-inc",
		Name:            "Acme Inc.",
		IsActive:        true,
		PrimaryColor:    "#4F46E5",
		BackgroundColor: "#FFFFFF",
		FontFamily:      "Inter",
	}
}

func savedWidget(layout, config string, active bool) *model.Widget {
	return &model.Widget{
		ID:       "33333333-3333-3333-3333-333333333333",
		Token:    "tok_abcdefghijklmnop",
		Name:     "Homepage wall",
		Layout:   layout,
		Config:   json.RawMessage(config),
		IsActive: active,
		Space:    *activeSpace(),
	}
}

func sampleWall() *model.Wall {
	return &model.Wall{
		Space: *activeSpace(),
		Testimonials: []model.Testimonial{
			{
				ID:         "22222222-2222-2222-2222-222222222222",
				AuthorName: "Alice Turner",
				Rating:     5,
				Content:    "Excellent.",
				IsFeatured: true,
			},
		},
		Count:         1,
		AverageRating: 5,
		GeneratedAt:   time.Now().UTC(),
	}
}

func newTestHandler(t *testing.T, s store.Store) (*Handler, *cache.Memory, *http.ServeMux) {
	t.Helper()

	memCache := cache.NewMemory(0)
	t.Cleanup(memCache.Close)

	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	h := New(s, memCache, logger, "")

	mux := http.NewServeMux()
	h.Routes(mux)
	h.TrackRoutes(mux)

	return h, memCache, mux
}

func do(mux *http.ServeMux, method, target, body string) *httptest.ResponseRecorder {
	var reader io.Reader
	if body != "" {
		reader = strings.NewReader(body)
	}

	request := httptest.NewRequest(method, target, reader)
	if body != "" {
		request.Header.Set("Content-Type", "application/json")
	}

	recorder := httptest.NewRecorder()
	mux.ServeHTTP(recorder, request)

	return recorder
}

func decodeBody[T any](t *testing.T, recorder *httptest.ResponseRecorder) T {
	t.Helper()

	var decoded T
	if err := json.Unmarshal(recorder.Body.Bytes(), &decoded); err != nil {
		t.Fatalf("response is not valid JSON: %v", err)
	}

	return decoded
}

// ── Health ──

func TestHealthOK(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{})

	if code := do(mux, http.MethodGet, "/health", "").Code; code != http.StatusOK {
		t.Fatalf("status = %d, want 200", code)
	}
}

func TestHealthDegradedWhenTheDatabaseIsDown(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{pingErr: errors.New("connection refused")})

	if code := do(mux, http.MethodGet, "/health", "").Code; code != http.StatusServiceUnavailable {
		t.Fatalf("status = %d, want 503", code)
	}
}

// ── Public reads ──

func TestSpaceReturnsThePublicProjection(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{space: activeSpace()})

	recorder := do(mux, http.MethodGet, "/api/public/spaces/acme-inc", "")

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d, want 200", recorder.Code)
	}

	if decoded := decodeBody[model.Space](t, recorder); decoded.Slug != "acme-inc" {
		t.Errorf("slug = %q", decoded.Slug)
	}
}

func TestSpaceNotFound(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{spaceErr: store.ErrNotFound})

	if code := do(mux, http.MethodGet, "/api/public/spaces/nope", "").Code; code != http.StatusNotFound {
		t.Fatalf("status = %d, want 404", code)
	}
}

func TestWallReturnsTestimonials(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{wall: sampleWall()})

	recorder := do(mux, http.MethodGet, "/api/public/spaces/acme-inc/wall", "")

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d, want 200", recorder.Code)
	}

	decoded := decodeBody[model.Wall](t, recorder)

	if decoded.Count != 1 || decoded.Testimonials[0].AuthorName != "Alice Turner" {
		t.Errorf("unexpected payload: %+v", decoded)
	}

	if recorder.Header().Get("X-Cache") != "miss" {
		t.Errorf("X-Cache = %q, want miss on the first call", recorder.Header().Get("X-Cache"))
	}
}

func TestWallIsServedFromCacheOnTheSecondCall(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{wall: sampleWall()})

	do(mux, http.MethodGet, "/api/public/spaces/acme-inc/wall", "")
	second := do(mux, http.MethodGet, "/api/public/spaces/acme-inc/wall", "")

	if second.Header().Get("X-Cache") != "hit" {
		t.Errorf("X-Cache = %q, want hit", second.Header().Get("X-Cache"))
	}
}

func TestWallReadsFiltersFromTheQuery(t *testing.T) {
	fake := &fakeStore{wall: sampleWall()}
	_, _, mux := newTestHandler(t, fake)

	do(mux, http.MethodGet, "/api/public/spaces/acme-inc/wall?limit=9&featured_only=1&min_rating=4", "")

	if fake.filterGot.Limit != 9 {
		t.Errorf("limit = %d, want 9", fake.filterGot.Limit)
	}
	if !fake.filterGot.FeaturedOnly {
		t.Error("featured_only should have been applied")
	}
	if fake.filterGot.MinRating != 4 {
		t.Errorf("min_rating = %d, want 4", fake.filterGot.MinRating)
	}
}

func TestWallIgnoresOutOfRangeFilters(t *testing.T) {
	fake := &fakeStore{wall: sampleWall()}
	_, _, mux := newTestHandler(t, fake)

	do(mux, http.MethodGet, "/api/public/spaces/acme-inc/wall?limit=9999&min_rating=9", "")

	if fake.filterGot.Limit != 50 {
		t.Errorf("limit = %d, want the default 50", fake.filterGot.Limit)
	}
	if fake.filterGot.MinRating != 0 {
		t.Errorf("min_rating = %d, want 0", fake.filterGot.MinRating)
	}
}

func TestWallCacheIsScopedByFilter(t *testing.T) {
	fake := &fakeStore{wall: sampleWall()}
	_, _, mux := newTestHandler(t, fake)

	do(mux, http.MethodGet, "/api/public/spaces/acme-inc/wall?limit=5", "")
	second := do(mux, http.MethodGet, "/api/public/spaces/acme-inc/wall?limit=10", "")

	// A different filter is a different cache entry.
	if second.Header().Get("X-Cache") != "miss" {
		t.Errorf("X-Cache = %q, want miss for a different filter", second.Header().Get("X-Cache"))
	}
	if fake.filterGot.Limit != 10 {
		t.Errorf("limit = %d, want 10", fake.filterGot.Limit)
	}
}

func TestWallNotFound(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{wallErr: store.ErrNotFound})

	if code := do(mux, http.MethodGet, "/api/public/spaces/nope/wall", "").Code; code != http.StatusNotFound {
		t.Fatalf("status = %d, want 404", code)
	}
}

func TestTestimonialsPathIsAnAliasForWall(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{wall: sampleWall()})

	if code := do(mux, http.MethodGet, "/api/public/spaces/acme-inc/testimonials", "").Code; code != http.StatusOK {
		t.Fatalf("status = %d, want 200", code)
	}
}

// ── Space-wide widget script ──

func TestWidgetServesJavaScript(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{space: activeSpace()})

	recorder := do(mux, http.MethodGet, "/w/acme-inc.js", "")

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d, want 200", recorder.Code)
	}

	if contentType := recorder.Header().Get("Content-Type"); !strings.Contains(contentType, "javascript") {
		t.Errorf("Content-Type = %q", contentType)
	}

	body := recorder.Body.String()
	if !strings.Contains(body, `"slug":"acme-inc"`) {
		t.Error("script is missing the slug")
	}
	if !strings.Contains(body, "/api/public/spaces/acme-inc/wall") {
		t.Error("script should point at the space wall endpoint")
	}
	if !strings.Contains(body, "attachShadow") {
		t.Error("script is missing the bundle")
	}
}

func TestWidgetAcceptsTheExtensionlessPath(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{space: activeSpace()})

	if code := do(mux, http.MethodGet, "/w/acme-inc", "").Code; code != http.StatusOK {
		t.Fatalf("status = %d, want 200", code)
	}
}

func TestWidgetPassesOptionsThrough(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{space: activeSpace()})

	recorder := do(mux, http.MethodGet, "/w/acme-inc.js?layout=carousel&theme=dark&columns=2", "")

	body := recorder.Body.String()
	for _, expected := range []string{`"layout":"carousel"`, `"theme":"dark"`, `"columns":2`} {
		if !strings.Contains(body, expected) {
			t.Errorf("script is missing %s", expected)
		}
	}
}

func TestWidgetForAnUnknownSpaceExplainsItself(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{spaceErr: store.ErrNotFound})

	recorder := do(mux, http.MethodGet, "/w/nope.js", "")

	if recorder.Code != http.StatusNotFound {
		t.Fatalf("status = %d, want 404", recorder.Code)
	}

	if !strings.Contains(recorder.Body.String(), "unknown or inactive widget") {
		t.Error("a 404 script should log a warning the developer can see")
	}
}

func TestWidgetUsesTheForwardedHost(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{space: activeSpace()})

	request := httptest.NewRequest(http.MethodGet, "/w/acme-inc.js", nil)
	request.Host = "internal:8080"
	request.Header.Set("X-Forwarded-Proto", "https")
	request.Header.Set("X-Forwarded-Host", "widgets.example.com")

	recorder := httptest.NewRecorder()
	mux.ServeHTTP(recorder, request)

	if !strings.Contains(recorder.Body.String(), `"api":"https://widgets.example.com"`) {
		t.Errorf("script should call back to the public origin, got: %s",
			firstLine(recorder.Body.String()))
	}
}

// ── Saved widget script ──

func TestSavedWidgetScriptCarriesItsToken(t *testing.T) {
	fake := &fakeStore{
		space:  activeSpace(),
		widget: savedWidget("badge", `{"theme":"dark","ratings":false}`, true),
	}
	_, _, mux := newTestHandler(t, fake)

	recorder := do(mux, http.MethodGet, "/w/acme-inc/tok_abcdefghijklmnop.js", "")

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d, want 200", recorder.Code)
	}

	body := recorder.Body.String()

	if !strings.Contains(body, `"token":"tok_abcdefghijklmnop"`) {
		t.Error("script is missing the token")
	}
	if !strings.Contains(body, "/api/public/widgets/tok_abcdefghijklmnop") {
		t.Error("script should point at the widget endpoint")
	}
	if !strings.Contains(body, `"layout":"badge"`) {
		t.Error("the stored layout should be applied")
	}
	if !strings.Contains(body, `"theme":"dark"`) {
		t.Error("the stored theme should be applied")
	}
	if !strings.Contains(body, `"ratings":false`) {
		t.Error("the stored ratings flag should be applied")
	}
}

func TestSavedWidgetScriptRejectsAMismatchedSlug(t *testing.T) {
	fake := &fakeStore{
		space:  activeSpace(),
		widget: savedWidget("masonry", `{}`, true),
	}
	_, _, mux := newTestHandler(t, fake)

	// The token belongs to acme-inc, not to this slug.
	recorder := do(mux, http.MethodGet, "/w/other-space/tok_abcdefghijklmnop.js", "")

	if recorder.Code != http.StatusNotFound {
		t.Fatalf("status = %d, want 404 for a mismatched slug", recorder.Code)
	}
}

func TestSavedWidgetScriptForAnInactiveWidget(t *testing.T) {
	fake := &fakeStore{
		space:  activeSpace(),
		widget: savedWidget("masonry", `{}`, false),
	}
	_, _, mux := newTestHandler(t, fake)

	if code := do(mux, http.MethodGet, "/w/acme-inc/tok_abcdefghijklmnop.js", "").Code; code != http.StatusNotFound {
		t.Fatalf("status = %d, want 404", code)
	}
}

func TestSavedWidgetScriptForAnUnknownToken(t *testing.T) {
	fake := &fakeStore{space: activeSpace(), widgetErr: store.ErrNotFound}
	_, _, mux := newTestHandler(t, fake)

	if code := do(mux, http.MethodGet, "/w/acme-inc/missing.js", "").Code; code != http.StatusNotFound {
		t.Fatalf("status = %d, want 404", code)
	}
}

// ── Saved widget data ──

func TestWidgetDataAppliesTheStoredConfiguration(t *testing.T) {
	fake := &fakeStore{
		widget: savedWidget("carousel", `{"limit":7,"featured_only":true,"min_rating":4}`, true),
		wall:   sampleWall(),
	}
	_, _, mux := newTestHandler(t, fake)

	recorder := do(mux, http.MethodGet, "/api/public/widgets/tok_abcdefghijklmnop", "")

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d, want 200", recorder.Code)
	}

	if fake.slugGot != "acme-inc" {
		t.Errorf("store was asked for %q, want the widget's space", fake.slugGot)
	}
	if fake.filterGot.Limit != 7 {
		t.Errorf("limit = %d, want 7", fake.filterGot.Limit)
	}
	if !fake.filterGot.FeaturedOnly {
		t.Error("featured_only should have been applied")
	}
	if fake.filterGot.MinRating != 4 {
		t.Errorf("min_rating = %d, want 4", fake.filterGot.MinRating)
	}

	// The bundle renders from these, so they must travel with the payload.
	decoded := decodeBody[model.Wall](t, recorder)

	if decoded.Options.Layout != "carousel" {
		t.Errorf("options.layout = %q", decoded.Options.Layout)
	}
	if decoded.Options.Limit != 7 {
		t.Errorf("options.limit = %d", decoded.Options.Limit)
	}
}

func TestWidgetDataForcesSingleLayoutToOne(t *testing.T) {
	fake := &fakeStore{
		widget: savedWidget("single", `{"limit":50,"featured_only":false}`, true),
		wall:   sampleWall(),
	}
	_, _, mux := newTestHandler(t, fake)

	do(mux, http.MethodGet, "/api/public/widgets/tok_abcdefghijklmnop", "")

	if fake.filterGot.Limit != 1 {
		t.Errorf("limit = %d, a single layout must ask for one", fake.filterGot.Limit)
	}
	if !fake.filterGot.FeaturedOnly {
		t.Error("a single layout should ask for the featured testimonial")
	}
}

func TestWidgetDataNotFound(t *testing.T) {
	fake := &fakeStore{widgetErr: store.ErrNotFound}
	_, _, mux := newTestHandler(t, fake)

	if code := do(mux, http.MethodGet, "/api/public/widgets/missing", "").Code; code != http.StatusNotFound {
		t.Fatalf("status = %d, want 404", code)
	}
}

func TestWidgetDataForAnInactiveWidget(t *testing.T) {
	fake := &fakeStore{widget: savedWidget("masonry", `{}`, false)}
	_, _, mux := newTestHandler(t, fake)

	if code := do(mux, http.MethodGet, "/api/public/widgets/tok_abcdefghijklmnop", "").Code; code != http.StatusNotFound {
		t.Fatalf("status = %d, want 404", code)
	}
}

func TestWidgetDataIsCachedPerWidget(t *testing.T) {
	fake := &fakeStore{
		widget: savedWidget("masonry", `{}`, true),
		wall:   sampleWall(),
	}
	_, _, mux := newTestHandler(t, fake)

	do(mux, http.MethodGet, "/api/public/widgets/tok_abcdefghijklmnop", "")
	second := do(mux, http.MethodGet, "/api/public/widgets/tok_abcdefghijklmnop", "")

	if second.Header().Get("X-Cache") != "hit" {
		t.Errorf("X-Cache = %q, want hit", second.Header().Get("X-Cache"))
	}
}

// ── Tracking ──

func TestTrackViewStoresTheImpression(t *testing.T) {
	fake := &fakeStore{}
	_, _, mux := newTestHandler(t, fake)

	body := `{"space_id":"11111111-1111-1111-1111-111111111111","referrer":"https://example.com","user_agent":"Mozilla/5.0"}`

	if code := do(mux, http.MethodPost, "/api/track/view", body).Code; code != http.StatusNoContent {
		t.Fatalf("status = %d, want 204", code)
	}

	if len(fake.views) != 1 {
		t.Fatalf("stored %d views, want 1", len(fake.views))
	}
	if fake.views[0].Referrer != "https://example.com" {
		t.Errorf("referrer = %q", fake.views[0].Referrer)
	}
	// The address must come from the connection, never from the payload.
	if fake.views[0].IPAddress == "" {
		t.Error("the connection IP should be recorded")
	}
}

func TestTrackViewIgnoresAClientSuppliedIP(t *testing.T) {
	fake := &fakeStore{}
	_, _, mux := newTestHandler(t, fake)

	do(mux, http.MethodPost, "/api/track/view",
		`{"space_id":"11111111-1111-1111-1111-111111111111","ip_address":"8.8.8.8"}`)

	if fake.views[0].IPAddress == "8.8.8.8" {
		t.Error("a spoofed ip_address must not be trusted")
	}
}

func TestTrackViewRequiresASpace(t *testing.T) {
	fake := &fakeStore{}
	_, _, mux := newTestHandler(t, fake)

	if code := do(mux, http.MethodPost, "/api/track/view", `{}`).Code; code != http.StatusUnprocessableEntity {
		t.Fatalf("status = %d, want 422", code)
	}
	if len(fake.views) != 0 {
		t.Error("nothing should have been stored")
	}
}

func TestTrackViewRejectsMalformedJSON(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{})

	if code := do(mux, http.MethodPost, "/api/track/view", `{"space_id":`).Code; code != http.StatusBadRequest {
		t.Fatalf("status = %d, want 400", code)
	}
}

func TestTrackClickStoresTheClick(t *testing.T) {
	fake := &fakeStore{}
	_, _, mux := newTestHandler(t, fake)

	body := `{"space_id":"11111111-1111-1111-1111-111111111111","testimonial_id":"22222222-2222-2222-2222-222222222222"}`

	if code := do(mux, http.MethodPost, "/api/track/click", body).Code; code != http.StatusNoContent {
		t.Fatalf("status = %d, want 204", code)
	}
	if len(fake.clicks) != 1 {
		t.Fatalf("stored %d clicks, want 1", len(fake.clicks))
	}
}

func TestTrackClickRequiresBothIdentifiers(t *testing.T) {
	_, _, mux := newTestHandler(t, &fakeStore{})

	body := `{"space_id":"11111111-1111-1111-1111-111111111111"}`

	if code := do(mux, http.MethodPost, "/api/track/click", body).Code; code != http.StatusUnprocessableEntity {
		t.Fatalf("status = %d, want 422", code)
	}
}

func TestTrackingStillAnswersWhenTheStoreFails(t *testing.T) {
	// A failing insert must not surface to the visitor's browser.
	_, _, mux := newTestHandler(t, &failingStore{})

	body := `{"space_id":"11111111-1111-1111-1111-111111111111"}`

	if code := do(mux, http.MethodPost, "/api/track/view", body).Code; code != http.StatusNoContent {
		t.Fatalf("status = %d, want 204 even on a write failure", code)
	}
}

type failingStore struct{ fakeStore }

func (f *failingStore) RecordView(context.Context, model.WidgetView) error {
	return errors.New("insert failed")
}

func (f *failingStore) RecordClick(context.Context, model.TestimonialClick) error {
	return errors.New("insert failed")
}

func firstLine(value string) string {
	if index := strings.Index(value, "\n"); index >= 0 {
		return value[:index]
	}

	return value
}
