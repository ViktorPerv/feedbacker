package model

import (
	"encoding/json"
	"time"
)

// Space is the public projection of a space: only what a widget needs.
type Space struct {
	ID              string    `json:"id"`
	Slug            string    `json:"slug"`
	Name            string    `json:"name"`
	IsActive        bool      `json:"is_active"`
	PrimaryColor    string    `json:"primary_color"`
	BackgroundColor string    `json:"background_color"`
	FontFamily      string    `json:"font_family"`
	LogoURL         string    `json:"logo_url"`
	CreatedAt       time.Time `json:"created_at"`
}

// Testimonial is the public projection of a testimonial.
//
// author_email is deliberately absent: it is collected on the collection page
// but never leaves the application.
type Testimonial struct {
	ID            string    `json:"id"`
	AuthorName    string    `json:"author_name"`
	AuthorAvatar  string    `json:"author_avatar_url,omitempty"`
	AuthorTitle   string    `json:"author_title,omitempty"`
	AuthorCompany string    `json:"author_company,omitempty"`
	Rating        int       `json:"rating"`
	Content       string    `json:"content"`
	VideoURL      string    `json:"video_url,omitempty"`
	IsFeatured    bool      `json:"is_featured"`
	CreatedAt     time.Time `json:"created_at"`
}

// EmbedOptions is everything the bundle needs to render, regardless of whether
// it came from a stored widget row or the script URL's query string.
type EmbedOptions struct {
	Layout       string `json:"layout"`
	Theme        string `json:"theme"`
	Columns      int    `json:"columns"`
	Limit        int    `json:"limit"`
	Accent       string `json:"accent,omitempty"`
	Font         string `json:"font,omitempty"`
	Heading      string `json:"heading,omitempty"`
	Ratings      bool   `json:"ratings"`
	Footer       bool   `json:"footer"`
	FeaturedOnly bool   `json:"featured_only"`
	MinRating    int    `json:"min_rating,omitempty"`
}

// Wall is the payload the embeddable widget renders.
type Wall struct {
	Space         Space         `json:"space"`
	Testimonials  []Testimonial `json:"testimonials"`
	Count         int           `json:"count"`
	AverageRating float64       `json:"average_rating"`
	Options       EmbedOptions  `json:"options"`
	GeneratedAt   time.Time     `json:"generated_at"`
}

// Widget is a saved embed configuration.
type Widget struct {
	ID       string          `json:"id"`
	Token    string          `json:"token"`
	Name     string          `json:"name"`
	Layout   string          `json:"layout"`
	Config   json.RawMessage `json:"config"`
	IsActive bool            `json:"is_active"`
	Space    Space           `json:"space"`
}

// WallFilter narrows the testimonials a wall contains.
type WallFilter struct {
	Limit        int
	FeaturedOnly bool
	MinRating    int
}

// WidgetView is one widget impression.
type WidgetView struct {
	SpaceID       string `json:"space_id"`
	TestimonialID string `json:"testimonial_id,omitempty"`
	Referrer      string `json:"referrer,omitempty"`
	UserAgent     string `json:"user_agent,omitempty"`
	IPAddress     string `json:"ip_address,omitempty"`
}

// TestimonialClick is one click on a card inside a widget.
type TestimonialClick struct {
	SpaceID       string `json:"space_id"`
	TestimonialID string `json:"testimonial_id"`
	Referrer      string `json:"referrer,omitempty"`
}
