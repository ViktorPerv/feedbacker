package store

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"net"
	"time"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgxpool"

	"widget-server/internal/model"
)

// Postgres is the pgx-backed Store.
type Postgres struct {
	pool *pgxpool.Pool
}

// NewPostgres connects and verifies the connection.
func NewPostgres(ctx context.Context, databaseURL string) (*Postgres, error) {
	config, err := pgxpool.ParseConfig(databaseURL)
	if err != nil {
		return nil, fmt.Errorf("parse database url: %w", err)
	}

	config.MaxConns = 20
	config.MinConns = 2
	config.MaxConnLifetime = time.Hour
	config.HealthCheckPeriod = 30 * time.Second

	pool, err := pgxpool.NewWithConfig(ctx, config)
	if err != nil {
		return nil, fmt.Errorf("create pool: %w", err)
	}

	pingCtx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()

	if err := pool.Ping(pingCtx); err != nil {
		pool.Close()
		return nil, fmt.Errorf("ping database: %w", err)
	}

	return &Postgres{pool: pool}, nil
}

func (p *Postgres) Close() { p.pool.Close() }

func (p *Postgres) Ping(ctx context.Context) error { return p.pool.Ping(ctx) }

const spaceColumns = `
	s.id::text, s.slug, s.name, s.is_active,
	COALESCE(b.primary_color, '#4F46E5'),
	COALESCE(b.background_color, '#FFFFFF'),
	COALESCE(b.font_family, 'Inter'),
	COALESCE(b.logo_url, ''),
	s.created_at
`

func scanSpace(row pgx.Row) (*model.Space, error) {
	var space model.Space

	err := row.Scan(
		&space.ID,
		&space.Slug,
		&space.Name,
		&space.IsActive,
		&space.PrimaryColor,
		&space.BackgroundColor,
		&space.FontFamily,
		&space.LogoURL,
		&space.CreatedAt,
	)

	return &space, err
}

func (p *Postgres) Space(ctx context.Context, slug string) (*model.Space, error) {
	query := `
		SELECT ` + spaceColumns + `
		FROM spaces s
		LEFT JOIN space_branding b ON b.space_id = s.id
		WHERE s.slug = $1
	`

	space, err := scanSpace(p.pool.QueryRow(ctx, query, slug))

	if errors.Is(err, pgx.ErrNoRows) {
		return nil, ErrNotFound
	}
	if err != nil {
		return nil, fmt.Errorf("query space: %w", err)
	}

	return space, nil
}

func (p *Postgres) Widget(ctx context.Context, token string) (*model.Widget, error) {
	query := `
		SELECT w.id::text, w.token, w.name, w.layout, w.config, w.is_active,
		       ` + spaceColumns + `
		FROM widgets w
		JOIN spaces s ON s.id = w.space_id
		LEFT JOIN space_branding b ON b.space_id = s.id
		WHERE w.token = $1
	`

	var widget model.Widget
	var config []byte

	err := p.pool.QueryRow(ctx, query, token).Scan(
		&widget.ID,
		&widget.Token,
		&widget.Name,
		&widget.Layout,
		&config,
		&widget.IsActive,
		&widget.Space.ID,
		&widget.Space.Slug,
		&widget.Space.Name,
		&widget.Space.IsActive,
		&widget.Space.PrimaryColor,
		&widget.Space.BackgroundColor,
		&widget.Space.FontFamily,
		&widget.Space.LogoURL,
		&widget.Space.CreatedAt,
	)

	if errors.Is(err, pgx.ErrNoRows) {
		return nil, ErrNotFound
	}
	if err != nil {
		return nil, fmt.Errorf("query widget: %w", err)
	}

	widget.Config = json.RawMessage(config)

	return &widget, nil
}

func (p *Postgres) Wall(ctx context.Context, slug string, filter model.WallFilter) (*model.Wall, error) {
	space, err := p.Space(ctx, slug)
	if err != nil {
		return nil, err
	}

	if !space.IsActive {
		return &model.Wall{
			Space:        *space,
			Testimonials: []model.Testimonial{},
			GeneratedAt:  time.Now().UTC(),
		}, nil
	}

	limit := filter.Limit
	if limit <= 0 || limit > 200 {
		limit = 50
	}

	// Filters are passed as bindings rather than concatenated, and the two
	// optional ones are expressed as "the filter is off OR the row matches",
	// which keeps a single prepared statement for every combination.
	const query = `
		SELECT t.id::text, t.author_name,
		       COALESCE(t.author_avatar_url, ''),
		       COALESCE(t.author_title, ''),
		       COALESCE(t.author_company, ''),
		       t.rating, t.content,
		       COALESCE(t.video_url, ''),
		       t.is_featured, t.created_at
		FROM testimonials t
		JOIN spaces s ON s.id = t.space_id
		WHERE s.slug = $1
		  AND s.is_active = true
		  AND t.status = 'approved'
		  AND ($2 = false OR t.is_featured = true)
		  AND ($3 = 0 OR t.rating >= $3)
		ORDER BY t.is_featured DESC, t.created_at DESC
		LIMIT $4
	`

	rows, err := p.pool.Query(ctx, query, slug, filter.FeaturedOnly, filter.MinRating, limit)
	if err != nil {
		return nil, fmt.Errorf("query testimonials: %w", err)
	}
	defer rows.Close()

	testimonials := make([]model.Testimonial, 0, limit)
	total := 0

	for rows.Next() {
		var t model.Testimonial
		if err := rows.Scan(
			&t.ID,
			&t.AuthorName,
			&t.AuthorAvatar,
			&t.AuthorTitle,
			&t.AuthorCompany,
			&t.Rating,
			&t.Content,
			&t.VideoURL,
			&t.IsFeatured,
			&t.CreatedAt,
		); err != nil {
			return nil, fmt.Errorf("scan testimonial: %w", err)
		}

		testimonials = append(testimonials, t)
		total += t.Rating
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("iterate testimonials: %w", err)
	}

	average := 0.0
	if len(testimonials) > 0 {
		average = float64(total) / float64(len(testimonials))
	}

	return &model.Wall{
		Space:         *space,
		Testimonials:  testimonials,
		Count:         len(testimonials),
		AverageRating: average,
		GeneratedAt:   time.Now().UTC(),
	}, nil
}

func (p *Postgres) RecordView(ctx context.Context, view model.WidgetView) error {
	if view.SpaceID == "" {
		return errors.New("space_id is required")
	}

	const query = `
		INSERT INTO widget_views (space_id, testimonial_id, referrer, user_agent, ip_address)
		VALUES ($1, $2, $3, $4, $5)
	`

	_, err := p.pool.Exec(ctx, query,
		view.SpaceID,
		nullableUUID(view.TestimonialID),
		nullIfEmpty(view.Referrer),
		nullIfEmpty(truncate(view.UserAgent, 500)),
		nullableInet(view.IPAddress),
	)
	if err != nil {
		return fmt.Errorf("insert widget view: %w", err)
	}

	return nil
}

func (p *Postgres) RecordClick(ctx context.Context, click model.TestimonialClick) error {
	if click.SpaceID == "" || click.TestimonialID == "" {
		return errors.New("space_id and testimonial_id are required")
	}

	const query = `
		INSERT INTO testimonial_clicks (space_id, testimonial_id, referrer)
		VALUES ($1, $2, $3)
	`

	_, err := p.pool.Exec(ctx, query,
		click.SpaceID,
		click.TestimonialID,
		nullIfEmpty(click.Referrer),
	)
	if err != nil {
		return fmt.Errorf("insert testimonial click: %w", err)
	}

	return nil
}

// ── helpers ──

// nullIfEmpty maps "" to SQL NULL so optional columns stay clean.
func nullIfEmpty(value string) any {
	if value == "" {
		return nil
	}

	return value
}

// nullableUUID guards against a malformed id reaching a uuid column.
func nullableUUID(value string) any {
	if value == "" || !looksLikeUUID(value) {
		return nil
	}

	return value
}

// nullableInet maps an invalid or empty address to SQL NULL.
func nullableInet(value string) any {
	if value == "" || net.ParseIP(value) == nil {
		return nil
	}

	return value
}

func looksLikeUUID(value string) bool {
	if len(value) != 36 {
		return false
	}

	for i, r := range value {
		switch i {
		case 8, 13, 18, 23:
			if r != '-' {
				return false
			}
		default:
			isHex := (r >= '0' && r <= '9') || (r >= 'a' && r <= 'f') || (r >= 'A' && r <= 'F')
			if !isHex {
				return false
			}
		}
	}

	return true
}

func truncate(value string, max int) string {
	if len(value) <= max {
		return value
	}

	return value[:max]
}
