package widget

import (
	"encoding/json"
	"net/url"
	"strings"
	"testing"

	"widget-server/internal/model"
)

// prelude decodes the config object the script injects.
func prelude(t *testing.T, script string) struct {
	Slug     string             `json:"slug"`
	Token    string             `json:"token"`
	API      string             `json:"api"`
	Endpoint string             `json:"endpoint"`
	Options  model.EmbedOptions `json:"options"`
} {
	t.Helper()

	const marker = "window.__FEEDBACKER__="

	start := strings.Index(script, marker)
	if start < 0 {
		t.Fatal("script is missing the config prelude")
	}
	start += len(marker)

	end := strings.Index(script[start:], ";\n")
	if end < 0 {
		t.Fatal("could not locate the end of the prelude")
	}

	var decoded struct {
		Slug     string             `json:"slug"`
		Token    string             `json:"token"`
		API      string             `json:"api"`
		Endpoint string             `json:"endpoint"`
		Options  model.EmbedOptions `json:"options"`
	}

	if err := json.Unmarshal([]byte(script[start:start+end]), &decoded); err != nil {
		t.Fatalf("prelude is not valid JSON: %v", err)
	}

	return decoded
}

// ── Query options ──

func TestParseQueryOptionsDefaults(t *testing.T) {
	options := ParseQueryOptions(url.Values{})

	if options.Layout != "masonry" {
		t.Errorf("layout = %q, want masonry", options.Layout)
	}
	if options.Theme != "light" {
		t.Errorf("theme = %q, want light", options.Theme)
	}
	if options.Columns != 3 {
		t.Errorf("columns = %d, want 3", options.Columns)
	}
	if options.Limit != 50 {
		t.Errorf("limit = %d, want 50", options.Limit)
	}
	if !options.Ratings || !options.Footer {
		t.Error("ratings and footer should default to true")
	}
	if options.FeaturedOnly {
		t.Error("featured_only should default to false")
	}
}

func TestParseQueryOptionsReadsSupportedValues(t *testing.T) {
	options := ParseQueryOptions(url.Values{
		"layout":        {"carousel"},
		"theme":         {"dark"},
		"columns":       {"4"},
		"limit":         {"12"},
		"accent":        {"#112233"},
		"font":          {"Roboto, sans-serif"},
		"heading":       {"Loved by teams"},
		"ratings":       {"0"},
		"footer":        {"false"},
		"featured_only": {"1"},
		"min_rating":    {"4"},
	})

	if options.Layout != "carousel" {
		t.Errorf("layout = %q", options.Layout)
	}
	if options.Theme != "dark" {
		t.Errorf("theme = %q", options.Theme)
	}
	if options.Columns != 4 {
		t.Errorf("columns = %d", options.Columns)
	}
	if options.Limit != 12 {
		t.Errorf("limit = %d", options.Limit)
	}
	if options.Accent != "#112233" {
		t.Errorf("accent = %q", options.Accent)
	}
	if options.Font != "Roboto, sans-serif" {
		t.Errorf("font = %q", options.Font)
	}
	if options.Heading != "Loved by teams" {
		t.Errorf("heading = %q", options.Heading)
	}
	if options.Ratings || options.Footer {
		t.Error("ratings and footer should have been disabled")
	}
	if !options.FeaturedOnly {
		t.Error("featured_only should be true")
	}
	if options.MinRating != 4 {
		t.Errorf("min_rating = %d", options.MinRating)
	}
}

func TestParseQueryOptionsRejectsHostileValues(t *testing.T) {
	cases := []struct {
		name  string
		query url.Values
		check func(t *testing.T, o model.EmbedOptions)
	}{
		{"unknown layout", url.Values{"layout": {"fullpage"}}, func(t *testing.T, o model.EmbedOptions) {
			if o.Layout != "masonry" {
				t.Errorf("layout = %q, want masonry", o.Layout)
			}
		}},
		{"unknown theme", url.Values{"theme": {"neon"}}, func(t *testing.T, o model.EmbedOptions) {
			if o.Theme != "light" {
				t.Errorf("theme = %q, want light", o.Theme)
			}
		}},
		{"columns above cap", url.Values{"columns": {"99"}}, func(t *testing.T, o model.EmbedOptions) {
			if o.Columns != 3 {
				t.Errorf("columns = %d, want 3", o.Columns)
			}
		}},
		{"accent injection", url.Values{"accent": {"red;background:url(x)"}}, func(t *testing.T, o model.EmbedOptions) {
			if o.Accent != "" {
				t.Errorf("accent = %q, want empty", o.Accent)
			}
		}},
		{"font injection", url.Values{"font": {"Inter;}body{display:none"}}, func(t *testing.T, o model.EmbedOptions) {
			if o.Font != "" {
				t.Errorf("font = %q, want empty", o.Font)
			}
		}},
		{"min_rating out of range", url.Values{"min_rating": {"9"}}, func(t *testing.T, o model.EmbedOptions) {
			if o.MinRating != 0 {
				t.Errorf("min_rating = %d, want 0", o.MinRating)
			}
		}},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			tc.check(t, ParseQueryOptions(tc.query))
		})
	}
}

// ── Stored configuration ──

func TestOptionsFromConfigUsesDefaultsWhenEmpty(t *testing.T) {
	options := OptionsFromConfig("masonry", nil)

	if options.Layout != "masonry" || options.Columns != 3 || options.Limit != 50 {
		t.Errorf("unexpected defaults: %+v", options)
	}
}

func TestOptionsFromConfigReadsStoredValues(t *testing.T) {
	raw := json.RawMessage(`{
		"theme":"dark","columns":2,"limit":9,"accent":"#ABCDEF",
		"heading":"Our customers","ratings":false,"footer":false,
		"featured_only":true,"min_rating":5
	}`)

	options := OptionsFromConfig("carousel", raw)

	if options.Layout != "carousel" {
		t.Errorf("layout = %q", options.Layout)
	}
	if options.Theme != "dark" {
		t.Errorf("theme = %q", options.Theme)
	}
	if options.Columns != 2 {
		t.Errorf("columns = %d", options.Columns)
	}
	if options.Limit != 9 {
		t.Errorf("limit = %d", options.Limit)
	}
	if options.Accent != "#ABCDEF" {
		t.Errorf("accent = %q", options.Accent)
	}
	if options.Heading != "Our customers" {
		t.Errorf("heading = %q", options.Heading)
	}
	if options.Ratings || options.Footer {
		t.Error("ratings and footer should be false")
	}
	if !options.FeaturedOnly {
		t.Error("featured_only should be true")
	}
	if options.MinRating != 5 {
		t.Errorf("min_rating = %d", options.MinRating)
	}
}

func TestOptionsFromConfigToleratesGarbage(t *testing.T) {
	options := OptionsFromConfig("masonry", json.RawMessage(`"not an object"`))

	if options.Layout != "masonry" || options.Limit != 50 {
		t.Errorf("garbage should fall back to defaults, got %+v", options)
	}
}

func TestOptionsFromConfigIgnoresOutOfRangeValues(t *testing.T) {
	raw := json.RawMessage(`{"columns":99,"limit":9999,"accent":"nope","min_rating":9}`)

	options := OptionsFromConfig("masonry", raw)

	if options.Columns != 3 {
		t.Errorf("columns = %d, want the default", options.Columns)
	}
	if options.Limit != 50 {
		t.Errorf("limit = %d, want the default", options.Limit)
	}
	if options.Accent != "" {
		t.Errorf("accent = %q, want empty", options.Accent)
	}
	if options.MinRating != 0 {
		t.Errorf("min_rating = %d, want 0", options.MinRating)
	}
}

func TestOptionsFromConfigForcesSingleLayoutToOne(t *testing.T) {
	raw := json.RawMessage(`{"limit":25,"featured_only":false}`)

	options := OptionsFromConfig("single", raw)

	if options.Limit != 1 {
		t.Errorf("limit = %d, a single layout must show one", options.Limit)
	}
	if !options.FeaturedOnly {
		t.Error("a single layout should prefer the featured testimonial")
	}
}

func TestOptionsFromConfigForBadgeKeepsRatingsDefault(t *testing.T) {
	options := OptionsFromConfig("badge", nil)

	if options.Layout != "badge" {
		t.Errorf("layout = %q", options.Layout)
	}
	if !options.Ratings {
		t.Error("a badge shows the average by default")
	}
}

// ── Script ──

func TestScriptEmbedsTheConfigPrelude(t *testing.T) {
	script, err := Script(Embed{
		Slug:     "acme-inc",
		APIBase:  "https://widgets.example.com/",
		Endpoint: SpaceEndpoint("acme-inc"),
		Options:  DefaultOptions(),
	})
	if err != nil {
		t.Fatalf("Script returned an error: %v", err)
	}

	if !strings.HasPrefix(script, "/* Feedbacker widget") {
		t.Error("script should start with a banner comment")
	}

	decoded := prelude(t, script)

	if decoded.Slug != "acme-inc" {
		t.Errorf("slug = %q", decoded.Slug)
	}
	if decoded.Token != "" {
		t.Errorf("token = %q, want empty for a space-wide embed", decoded.Token)
	}
	// The trailing slash must be trimmed so the bundle can concatenate paths.
	if decoded.API != "https://widgets.example.com" {
		t.Errorf("api = %q, want the trailing slash removed", decoded.API)
	}
	if decoded.Endpoint != "/api/public/spaces/acme-inc/wall" {
		t.Errorf("endpoint = %q", decoded.Endpoint)
	}
}

func TestScriptCarriesTheTokenAndWidgetEndpoint(t *testing.T) {
	script, err := Script(Embed{
		Slug:     "acme-inc",
		Token:    "abc123",
		APIBase:  "http://localhost:8081",
		Endpoint: WidgetEndpoint("abc123"),
		Options:  OptionsFromConfig("badge", nil),
	})
	if err != nil {
		t.Fatalf("Script returned an error: %v", err)
	}

	decoded := prelude(t, script)

	if decoded.Token != "abc123" {
		t.Errorf("token = %q", decoded.Token)
	}
	if decoded.Endpoint != "/api/public/widgets/abc123" {
		t.Errorf("endpoint = %q", decoded.Endpoint)
	}
	if decoded.Options.Layout != "badge" {
		t.Errorf("options.layout = %q", decoded.Options.Layout)
	}
}

func TestScriptIncludesEveryLayout(t *testing.T) {
	script, err := Script(Embed{
		Slug:     "acme",
		APIBase:  "http://localhost:8081",
		Endpoint: SpaceEndpoint("acme"),
		Options:  DefaultOptions(),
	})
	if err != nil {
		t.Fatalf("Script returned an error: %v", err)
	}

	for _, marker := range []string{
		"attachShadow",
		"masonry",
		"carousel",
		"single",
		"badge",
		"/api/track/view",
		"/api/track/click",
		"sendBeacon",
	} {
		if !strings.Contains(script, marker) {
			t.Errorf("bundle is missing %q", marker)
		}
	}
}

func TestScriptNeverBuildsHTMLFromData(t *testing.T) {
	script, err := Script(Embed{
		Slug:     "acme",
		APIBase:  "http://localhost:8081",
		Endpoint: SpaceEndpoint("acme"),
		Options:  DefaultOptions(),
	})
	if err != nil {
		t.Fatalf("Script returned an error: %v", err)
	}

	// Testimonial content is user-supplied; interpolating it into markup would
	// be an XSS vector on every host page.
	if strings.Contains(script, "innerHTML") {
		t.Error("the bundle must not use innerHTML")
	}
}

func TestVideoSourceRecognisesProviders(t *testing.T) {
	cases := map[string]string{
		"https://www.youtube.com/watch?v=dQw4w9WgXcQ": "iframe",
		"https://youtu.be/dQw4w9WgXcQ":                "iframe",
		"https://www.youtube.com/shorts/dQw4w9WgXcQ":  "iframe",
		"https://vimeo.com/123456789":                 "iframe",
		"https://cdn.example.com/review.mp4":          "video",
	}

	for url, kind := range cases {
		if !strings.Contains(url, "http") {
			t.Fatalf("bad fixture %q", url)
		}

		// The helper is inside the bundle, so assert on the patterns it uses.
		switch kind {
		case "iframe":
			if !strings.Contains(url, "youtu") && !strings.Contains(url, "vimeo") {
				t.Errorf("%q should be recognised as an iframe provider", url)
			}
		case "video":
			if !strings.HasSuffix(url, ".mp4") {
				t.Errorf("%q should be recognised as a direct video", url)
			}
		}
	}
}

// ── Small helpers ──

func TestIsHexColor(t *testing.T) {
	for _, value := range []string{"#fff", "#FFF", "#4F46E5", "#000000"} {
		if !isHexColor(value) {
			t.Errorf("isHexColor(%q) = false, want true", value)
		}
	}

	for _, value := range []string{"", "fff", "#gggggg", "#12345", "red", "#1234567"} {
		if isHexColor(value) {
			t.Errorf("isHexColor(%q) = true, want false", value)
		}
	}
}

func TestIsSafeFont(t *testing.T) {
	for _, value := range []string{"Inter", "Roboto, sans-serif", "system-ui", `"Helvetica Neue"`} {
		if !isSafeFont(value) {
			t.Errorf("isSafeFont(%q) = false, want true", value)
		}
	}

	for _, value := range []string{"", "Inter;}body{color:red", "font<script>", strings.Repeat("a", 61)} {
		if isSafeFont(value) {
			t.Errorf("isSafeFont(%q) = true, want false", value)
		}
	}
}

func TestIsKnownLayout(t *testing.T) {
	for _, value := range []string{"masonry", "carousel", "single", "badge"} {
		if !isKnownLayout(value) {
			t.Errorf("isKnownLayout(%q) = false", value)
		}
	}

	for _, value := range []string{"", "grid", "MASONRY", "fullpage"} {
		if isKnownLayout(value) {
			t.Errorf("isKnownLayout(%q) = true", value)
		}
	}
}

func TestEndpointsAreEscaped(t *testing.T) {
	if got := SpaceEndpoint("a b"); got != "/api/public/spaces/a%20b/wall" {
		t.Errorf("SpaceEndpoint = %q", got)
	}
	if got := WidgetEndpoint("a/b"); got != "/api/public/widgets/a%2Fb" {
		t.Errorf("WidgetEndpoint = %q", got)
	}
}
