// Package handler exposes the public HTTP surface of the widget service.
package handler

import (
	"encoding/json"
	"errors"
	"log/slog"
	"net/http"
	"strconv"
	"strings"
	"time"

	"widget-server/internal/cache"
	"widget-server/internal/middleware"
	"widget-server/internal/model"
	"widget-server/internal/store"
	"widget-server/internal/widget"
)

const (
	defaultWallTTL = 60 * time.Second
	maxUserAgent   = 500
	maxReferrer    = 1000
	maxBodyBytes   = 64 * 1024
)

// Handler serves every public route.
type Handler struct {
	store  store.Store
	cache  cache.Cache
	logger *slog.Logger

	// apiBase is the absolute origin the embed script should call back to.
	// When empty the request host is used, which keeps local development
	// working without extra configuration.
	apiBase string

	wallTTL time.Duration
}

// New builds a Handler.
func New(s store.Store, c cache.Cache, logger *slog.Logger, apiBase string) *Handler {
	return &Handler{
		store:   s,
		cache:   c,
		logger:  logger,
		apiBase: strings.TrimRight(apiBase, "/"),
		wallTTL: defaultWallTTL,
	}
}

// WithWallTTL overrides the wall cache lifetime.
func (h *Handler) WithWallTTL(ttl time.Duration) *Handler {
	h.wallTTL = ttl
	return h
}

// Routes registers the read endpoints. Tracking is registered separately by
// the caller so it can sit behind the rate limiter.
func (h *Handler) Routes(mux *http.ServeMux) {
	mux.HandleFunc("GET /health", h.Health)

	mux.HandleFunc("GET /api/public/spaces/{slug}", h.Space)
	mux.HandleFunc("GET /api/public/spaces/{slug}/wall", h.Wall)
	mux.HandleFunc("GET /api/public/spaces/{slug}/testimonials", h.Wall)

	mux.HandleFunc("GET /api/public/widgets/{token}", h.WidgetData)

	mux.HandleFunc("GET /w/{slug}", h.WidgetScript)
	mux.HandleFunc("GET /w/{slug}/{token}", h.WidgetScriptByToken)
}

// TrackRoutes registers the two write endpoints.
func (h *Handler) TrackRoutes(mux *http.ServeMux) {
	mux.HandleFunc("POST /api/track/view", h.TrackView)
	mux.HandleFunc("POST /api/track/click", h.TrackClick)
}

// ── Health ──

func (h *Handler) Health(w http.ResponseWriter, r *http.Request) {
	if err := h.store.Ping(r.Context()); err != nil {
		h.logger.Error("health check failed", "error", err)
		writeJSON(w, http.StatusServiceUnavailable, map[string]string{"status": "degraded"})
		return
	}

	writeJSON(w, http.StatusOK, map[string]string{"status": "ok"})
}

// ── Public reads ──

func (h *Handler) Space(w http.ResponseWriter, r *http.Request) {
	slug := r.PathValue("slug")

	space, err := h.store.Space(r.Context(), slug)
	if errors.Is(err, store.ErrNotFound) {
		writeError(w, http.StatusNotFound, "space not found")
		return
	}
	if err != nil {
		h.logger.Error("load space", "slug", slug, "error", err)
		writeError(w, http.StatusInternalServerError, "internal error")
		return
	}

	writeJSON(w, http.StatusOK, space)
}

// Wall returns the approved testimonials for a space.
//
// Query parameters narrow the result, which is what a space-wide embed uses to
// apply its URL configuration without a saved widget row.
func (h *Handler) Wall(w http.ResponseWriter, r *http.Request) {
	slug := r.PathValue("slug")
	filter := filterFromQuery(r)

	h.serveWall(w, r, "space:"+slug, filter, func() (*model.Wall, error) {
		return h.store.Wall(r.Context(), slug, filter)
	})
}

// WidgetData returns the wall for a saved widget, with its stored filters.
func (h *Handler) WidgetData(w http.ResponseWriter, r *http.Request) {
	token := r.PathValue("token")

	saved, err := h.store.Widget(r.Context(), token)
	if errors.Is(err, store.ErrNotFound) {
		writeError(w, http.StatusNotFound, "widget not found")
		return
	}
	if err != nil {
		h.logger.Error("load widget", "token", token, "error", err)
		writeError(w, http.StatusInternalServerError, "internal error")
		return
	}

	if !saved.IsActive {
		writeError(w, http.StatusNotFound, "widget is inactive")
		return
	}

	options := widget.OptionsFromConfig(saved.Layout, saved.Config)
	filter := model.WallFilter{
		Limit:        options.Limit,
		FeaturedOnly: options.FeaturedOnly,
		MinRating:    options.MinRating,
	}

	h.serveWall(w, r, "widget:"+token, filter, func() (*model.Wall, error) {
		wall, err := h.store.Wall(r.Context(), saved.Space.Slug, filter)
		if err != nil {
			return nil, err
		}

		// The bundle renders from these, so they travel with the payload.
		wall.Options = options

		return wall, nil
	})
}

// serveWall centralises caching, marshalling and error mapping for both wall
// endpoints.
func (h *Handler) serveWall(
	w http.ResponseWriter,
	r *http.Request,
	cacheKey string,
	filter model.WallFilter,
	load func() (*model.Wall, error),
) {
	key := cacheKey + ":" + strconv.Itoa(filter.Limit) +
		":" + strconv.FormatBool(filter.FeaturedOnly) +
		":" + strconv.Itoa(filter.MinRating)

	if payload, ok := h.cache.Get(r.Context(), key); ok {
		writeCached(w, payload, "hit")
		return
	}

	wall, err := load()
	if errors.Is(err, store.ErrNotFound) {
		writeError(w, http.StatusNotFound, "space not found")
		return
	}
	if err != nil {
		h.logger.Error("load wall", "key", cacheKey, "error", err)
		writeError(w, http.StatusInternalServerError, "internal error")
		return
	}

	payload, err := json.Marshal(wall)
	if err != nil {
		h.logger.Error("marshal wall", "key", cacheKey, "error", err)
		writeError(w, http.StatusInternalServerError, "internal error")
		return
	}

	h.cache.Set(r.Context(), key, payload, h.wallTTL)

	writeCached(w, payload, "miss")
}

// ── Widget scripts ──

// WidgetScript serves the space-wide embed, configured by its URL.
func (h *Handler) WidgetScript(w http.ResponseWriter, r *http.Request) {
	slug := strings.TrimSuffix(r.PathValue("slug"), ".js")

	// Fail fast for unknown slugs so a typo does not silently render nothing.
	if _, err := h.store.Space(r.Context(), slug); err != nil {
		h.writeScriptError(w, slug, err)
		return
	}

	h.writeScript(w, widget.Embed{
		Slug:     slug,
		APIBase:  h.resolveAPIBase(r),
		Endpoint: widget.SpaceEndpoint(slug),
		Options:  widget.ParseQueryOptions(r.URL.Query()),
	})
}

// WidgetScriptByToken serves a saved widget, configured by its row.
func (h *Handler) WidgetScriptByToken(w http.ResponseWriter, r *http.Request) {
	slug := r.PathValue("slug")
	token := strings.TrimSuffix(r.PathValue("token"), ".js")

	saved, err := h.store.Widget(r.Context(), token)
	if err != nil {
		h.writeScriptError(w, token, err)
		return
	}

	// The slug in the URL is decoration for readability, but it must agree
	// with the token so a stale snippet cannot silently serve another space.
	if saved.Space.Slug != slug {
		h.logger.Warn("widget slug mismatch",
			"token", token,
			"expected", saved.Space.Slug,
			"got", slug,
		)
		h.writeScriptError(w, token, store.ErrNotFound)
		return
	}

	if !saved.IsActive {
		h.writeScriptError(w, token, store.ErrNotFound)
		return
	}

	h.writeScript(w, widget.Embed{
		Slug:     slug,
		Token:    token,
		APIBase:  h.resolveAPIBase(r),
		Endpoint: widget.WidgetEndpoint(token),
		Options:  widget.OptionsFromConfig(saved.Layout, saved.Config),
	})
}

func (h *Handler) writeScript(w http.ResponseWriter, embed widget.Embed) {
	script, err := widget.Script(embed)
	if err != nil {
		h.logger.Error("build widget script", "slug", embed.Slug, "error", err)
		writeError(w, http.StatusInternalServerError, "internal error")
		return
	}

	w.Header().Set("Content-Type", "application/javascript; charset=utf-8")
	w.Header().Set("Cache-Control", "public, max-age=300")
	w.Header().Set("X-Content-Type-Options", "nosniff")
	w.WriteHeader(http.StatusOK)
	w.Write([]byte(script))
}

// writeScriptError answers with JavaScript rather than JSON: the browser is
// loading this as a script, and a parse error in the console is less useful
// than a readable warning.
func (h *Handler) writeScriptError(w http.ResponseWriter, identifier string, err error) {
	if !errors.Is(err, store.ErrNotFound) {
		h.logger.Error("load space for widget", "identifier", identifier, "error", err)
		writeError(w, http.StatusInternalServerError, "internal error")
		return
	}

	w.Header().Set("Content-Type", "application/javascript; charset=utf-8")
	w.WriteHeader(http.StatusNotFound)
	w.Write([]byte(`console.warn("[feedbacker] unknown or inactive widget: ` + identifier + `");`))
}

// ── Tracking ──

func (h *Handler) TrackView(w http.ResponseWriter, r *http.Request) {
	var view model.WidgetView
	if err := decodeJSON(w, r, &view); err != nil {
		writeError(w, http.StatusBadRequest, "invalid json")
		return
	}

	if view.SpaceID == "" {
		writeError(w, http.StatusUnprocessableEntity, "space_id is required")
		return
	}

	// The client-supplied address is ignored: only the connection's own IP is
	// trustworthy.
	view.IPAddress = middleware.ClientIP(r)
	view.Referrer = truncate(view.Referrer, maxReferrer)
	view.UserAgent = truncate(view.UserAgent, maxUserAgent)

	if err := h.store.RecordView(r.Context(), view); err != nil {
		// Analytics failures must never surface to the visitor.
		h.logger.Warn("record view", "error", err)
	}

	w.WriteHeader(http.StatusNoContent)
}

func (h *Handler) TrackClick(w http.ResponseWriter, r *http.Request) {
	var click model.TestimonialClick
	if err := decodeJSON(w, r, &click); err != nil {
		writeError(w, http.StatusBadRequest, "invalid json")
		return
	}

	if click.SpaceID == "" || click.TestimonialID == "" {
		writeError(w, http.StatusUnprocessableEntity, "space_id and testimonial_id are required")
		return
	}

	click.Referrer = truncate(click.Referrer, maxReferrer)

	if err := h.store.RecordClick(r.Context(), click); err != nil {
		h.logger.Warn("record click", "error", err)
	}

	w.WriteHeader(http.StatusNoContent)
}

// ── helpers ──

func filterFromQuery(r *http.Request) model.WallFilter {
	query := r.URL.Query()

	filter := model.WallFilter{Limit: 50}

	if limit, err := strconv.Atoi(query.Get("limit")); err == nil && limit >= 1 && limit <= 200 {
		filter.Limit = limit
	}

	if rating, err := strconv.Atoi(query.Get("min_rating")); err == nil && rating >= 1 && rating <= 5 {
		filter.MinRating = rating
	}

	switch strings.ToLower(query.Get("featured_only")) {
	case "1", "true", "yes":
		filter.FeaturedOnly = true
	}

	return filter
}

// resolveAPIBase works out the origin the embed should call back to.
func (h *Handler) resolveAPIBase(r *http.Request) string {
	if h.apiBase != "" {
		return h.apiBase
	}

	scheme := "http"
	if r.TLS != nil {
		scheme = "https"
	}
	if forwarded := r.Header.Get("X-Forwarded-Proto"); forwarded != "" {
		scheme = strings.TrimSpace(strings.Split(forwarded, ",")[0])
	}

	host := r.Host
	if forwarded := r.Header.Get("X-Forwarded-Host"); forwarded != "" {
		host = strings.TrimSpace(strings.Split(forwarded, ",")[0])
	}

	return scheme + "://" + host
}

func decodeJSON(w http.ResponseWriter, r *http.Request, target any) error {
	decoder := json.NewDecoder(http.MaxBytesReader(w, r.Body, maxBodyBytes))
	decoder.DisallowUnknownFields()

	return decoder.Decode(target)
}

func truncate(value string, max int) string {
	if len(value) <= max {
		return value
	}

	return value[:max]
}

func writeCached(w http.ResponseWriter, payload []byte, cacheState string) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.Header().Set("Cache-Control", "public, max-age=30")
	w.Header().Set("X-Cache", cacheState)
	w.WriteHeader(http.StatusOK)
	w.Write(payload)
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(payload)
}

func writeError(w http.ResponseWriter, status int, message string) {
	writeJSON(w, status, map[string]string{"error": message})
}
