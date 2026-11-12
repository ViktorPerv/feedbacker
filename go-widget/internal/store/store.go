package store

import (
	"context"
	"errors"

	"widget-server/internal/model"
)

// ErrNotFound is returned when a space slug or widget token does not resolve.
var ErrNotFound = errors.New("not found")

// Store is the persistence boundary for the widget service.
//
// Handlers depend on this interface rather than on pgx directly, which keeps
// them testable without a database.
type Store interface {
	// Space returns a space by slug, active or not.
	Space(ctx context.Context, slug string) (*model.Space, error)

	// Widget returns a saved widget by its public token, with its space.
	Widget(ctx context.Context, token string) (*model.Widget, error)

	// Wall returns the public payload for a slug: the space plus its approved
	// testimonials, featured first, narrowed by filter.
	//
	// ErrNotFound when the slug is unknown.
	Wall(ctx context.Context, slug string, filter model.WallFilter) (*model.Wall, error)

	// RecordView stores one widget impression.
	RecordView(ctx context.Context, view model.WidgetView) error

	// RecordClick stores one click on a testimonial card.
	RecordClick(ctx context.Context, click model.TestimonialClick) error

	// Ping checks connectivity.
	Ping(ctx context.Context) error

	// Close releases resources.
	Close()
}
