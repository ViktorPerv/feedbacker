// Package widget serves the embeddable Wall of Love script.
package widget

import (
	_ "embed"
	"encoding/json"
	"fmt"
	"net/url"
	"strconv"
	"strings"

	"widget-server/internal/model"
)

//go:embed embed.js
var embedScript string

// Defaults mirror WidgetLayoutEnum::defaultConfig() on the Laravel side.
func DefaultOptions() model.EmbedOptions {
	return model.EmbedOptions{
		Layout:  "masonry",
		Theme:   "light",
		Columns: 3,
		Limit:   50,
		Ratings: true,
		Footer:  true,
	}
}

// ParseQueryOptions reads the supported parameters from a script URL.
//
// Anything missing or out of range falls back to its default. This is the path
// used by `/w/{slug}.js`, where the embed is configured entirely by URL.
func ParseQueryOptions(query url.Values) model.EmbedOptions {
	options := DefaultOptions()

	if layout := strings.ToLower(query.Get("layout")); isKnownLayout(layout) {
		options.Layout = layout
	}

	if theme := strings.ToLower(query.Get("theme")); theme == "dark" || theme == "light" {
		options.Theme = theme
	}

	if columns, err := strconv.Atoi(query.Get("columns")); err == nil && columns >= 1 && columns <= 6 {
		options.Columns = columns
	}

	if limit, err := strconv.Atoi(query.Get("limit")); err == nil && limit >= 1 && limit <= 200 {
		options.Limit = limit
	}

	if accent := strings.TrimSpace(query.Get("accent")); isHexColor(accent) {
		options.Accent = accent
	}

	if font := strings.TrimSpace(query.Get("font")); isSafeFont(font) {
		options.Font = font
	}

	if heading := strings.TrimSpace(query.Get("heading")); heading != "" && len(heading) <= 120 {
		options.Heading = heading
	}

	options.Ratings = parseBool(query.Get("ratings"), true)
	options.Footer = parseBool(query.Get("footer"), true)
	options.FeaturedOnly = parseBool(query.Get("featured_only"), false)

	if rating, err := strconv.Atoi(query.Get("min_rating")); err == nil && rating >= 1 && rating <= 5 {
		options.MinRating = rating
	}

	return options
}

// OptionsFromConfig turns a stored widget row into render options.
//
// The row was validated by Laravel before it was written, so this only has to
// be defensive about shape: unknown keys are ignored and missing ones fall back
// to the layout's defaults, which keeps a widget written before a new option
// existed rendering correctly.
func OptionsFromConfig(layout string, raw json.RawMessage) model.EmbedOptions {
	options := DefaultOptions()

	if isKnownLayout(layout) {
		options.Layout = layout
	}

	// A single-item layout makes no sense without a limit of one.
	if options.Layout == "single" {
		options.Limit = 1
		options.FeaturedOnly = true
	}

	if len(raw) == 0 {
		return options
	}

	var stored struct {
		Theme        *string `json:"theme"`
		Columns      *int    `json:"columns"`
		Limit        *int    `json:"limit"`
		Accent       *string `json:"accent"`
		Heading      *string `json:"heading"`
		Ratings      *bool   `json:"ratings"`
		Footer       *bool   `json:"footer"`
		FeaturedOnly *bool   `json:"featured_only"`
		MinRating    *int    `json:"min_rating"`
	}

	if err := json.Unmarshal(raw, &stored); err != nil {
		return options
	}

	if stored.Theme != nil && (*stored.Theme == "dark" || *stored.Theme == "light") {
		options.Theme = *stored.Theme
	}

	if stored.Columns != nil && *stored.Columns >= 1 && *stored.Columns <= 6 {
		options.Columns = *stored.Columns
	}

	if stored.Limit != nil && *stored.Limit >= 1 && *stored.Limit <= 200 {
		options.Limit = *stored.Limit
	}

	if stored.Accent != nil && isHexColor(*stored.Accent) {
		options.Accent = *stored.Accent
	}

	if stored.Heading != nil && len(*stored.Heading) <= 120 {
		options.Heading = *stored.Heading
	}

	if stored.Ratings != nil {
		options.Ratings = *stored.Ratings
	}

	if stored.Footer != nil {
		options.Footer = *stored.Footer
	}

	if stored.FeaturedOnly != nil {
		options.FeaturedOnly = *stored.FeaturedOnly
	}

	if stored.MinRating != nil && *stored.MinRating >= 1 && *stored.MinRating <= 5 {
		options.MinRating = *stored.MinRating
	}

	// Layout rules win over stored values: a `single` widget must show one.
	if options.Layout == "single" {
		options.Limit = 1
		options.FeaturedOnly = true
	}

	return options
}

// Embed describes one script response.
type Embed struct {
	// Slug is the space, used for the data attribute the bundle looks for.
	Slug string
	// Token is set for a saved widget, empty for the space-wide embed.
	Token string
	// APIBase is the absolute origin the bundle should call back to.
	APIBase string
	// Endpoint is the path the bundle fetches its data from.
	Endpoint string
	// Options are the resolved render options.
	Options model.EmbedOptions
}

// config is the object injected as window.__FEEDBACKER__.
type config struct {
	Slug     string             `json:"slug"`
	Token    string             `json:"token,omitempty"`
	API      string             `json:"api"`
	Endpoint string             `json:"endpoint"`
	Options  model.EmbedOptions `json:"options"`
}

// Script returns the embeddable bundle with its configuration prelude.
func Script(embed Embed) (string, error) {
	payload, err := json.Marshal(config{
		Slug:     embed.Slug,
		Token:    embed.Token,
		API:      strings.TrimRight(embed.APIBase, "/"),
		Endpoint: embed.Endpoint,
		Options:  embed.Options,
	})
	if err != nil {
		return "", fmt.Errorf("marshal widget config: %w", err)
	}

	var builder strings.Builder

	builder.WriteString("/* Feedbacker widget — ")
	builder.WriteString(embed.Slug)
	if embed.Token != "" {
		builder.WriteString("/")
		builder.WriteString(embed.Token)
	}
	builder.WriteString(" */\n")
	builder.WriteString("window.__FEEDBACKER__=")
	builder.Write(payload)
	builder.WriteString(";\n")
	builder.WriteString(embedScript)

	return builder.String(), nil
}

// SpaceEndpoint is where a space-wide embed fetches its data.
func SpaceEndpoint(slug string) string {
	return "/api/public/spaces/" + url.PathEscape(slug) + "/wall"
}

// WidgetEndpoint is where a saved widget fetches its data.
func WidgetEndpoint(token string) string {
	return "/api/public/widgets/" + url.PathEscape(token)
}

// ── validation helpers ──

func isKnownLayout(value string) bool {
	switch value {
	case "masonry", "carousel", "single", "badge":
		return true
	default:
		return false
	}
}

// isHexColor accepts #RGB and #RRGGBB.
func isHexColor(value string) bool {
	if len(value) != 4 && len(value) != 7 {
		return false
	}

	if value[0] != '#' {
		return false
	}

	for _, r := range value[1:] {
		isHex := (r >= '0' && r <= '9') || (r >= 'a' && r <= 'f') || (r >= 'A' && r <= 'F')
		if !isHex {
			return false
		}
	}

	return true
}

// isSafeFont rejects anything that could break out of the CSS declaration the
// value is interpolated into.
func isSafeFont(value string) bool {
	if value == "" || len(value) > 60 {
		return false
	}

	for _, r := range value {
		isAllowed := (r >= 'a' && r <= 'z') ||
			(r >= 'A' && r <= 'Z') ||
			(r >= '0' && r <= '9') ||
			r == ' ' || r == '-' || r == '_' || r == ',' || r == '"' || r == '\''

		if !isAllowed {
			return false
		}
	}

	return true
}

// parseBool treats anything other than an explicit negative as true when the
// parameter is absent.
func parseBool(raw string, fallback bool) bool {
	switch strings.ToLower(raw) {
	case "":
		return fallback
	case "0", "false", "no", "off":
		return false
	default:
		return true
	}
}
