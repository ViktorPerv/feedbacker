package main

import (
	"context"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"strings"
	"syscall"
	"time"

	"widget-server/internal/cache"
	"widget-server/internal/handler"
	"widget-server/internal/middleware"
	"widget-server/internal/store"
)

func main() {
	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
		Level: logLevel(env("LOG_LEVEL", "info")),
	}))

	port := env("SERVER_PORT", "8080")
	databaseURL := env("DATABASE_URL", "postgres://feedbacker:secret@localhost:5432/feedbacker?sslmode=disable")
	apiBase := env("PUBLIC_API_BASE", "")
	allowedOrigins := env("WIDGET_ORIGINS", "*")

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	pg, err := store.NewPostgres(ctx, databaseURL)
	if err != nil {
		logger.Error("database connection failed", "error", err)
		os.Exit(1)
	}
	defer pg.Close()

	logger.Info("connected to postgres")

	memCache := cache.NewMemory(2 * time.Minute)
	defer memCache.Close()

	// Public write endpoints are open to the internet, so they get their own
	// budget. Reads are not throttled: widget impressions are exactly the
	// traffic that must stay fast.
	trackLimiter := middleware.NewRateLimiter(60, time.Minute)
	defer trackLimiter.Close()

	h := handler.New(pg, memCache, logger, apiBase)

	mux := http.NewServeMux()
	h.Routes(mux)

	limitedView := middleware.Limit(trackLimiter, "view")(http.HandlerFunc(h.TrackView))
	limitedClick := middleware.Limit(trackLimiter, "click")(http.HandlerFunc(h.TrackClick))

	mux.Handle("POST /api/track/view", limitedView)
	mux.Handle("POST /api/track/click", limitedClick)

	var root http.Handler = mux
	root = middleware.CORS(allowedOrigins)(root)
	root = middleware.Logging(logger)(root)
	root = middleware.Recover(logger)(root)

	srv := &http.Server{
		Addr:              ":" + port,
		Handler:           root,
		ReadTimeout:       10 * time.Second,
		ReadHeaderTimeout: 5 * time.Second,
		WriteTimeout:      15 * time.Second,
		IdleTimeout:       60 * time.Second,
	}

	go func() {
		<-ctx.Done()
		logger.Info("shutdown signal received")

		shutdownCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
		defer cancel()

		if err := srv.Shutdown(shutdownCtx); err != nil {
			logger.Error("graceful shutdown failed", "error", err)
		}
	}()

	logger.Info("widget server listening", "port", port, "api_base", apiBase)

	err = srv.ListenAndServe()
	if err != nil && !errors.Is(err, http.ErrServerClosed) {
		logger.Error("server error", "error", err)
		os.Exit(1)
	}

	logger.Info("server stopped")
}

func env(key, fallback string) string {
	if value := strings.TrimSpace(os.Getenv(key)); value != "" {
		return value
	}

	return fallback
}

func logLevel(raw string) slog.Level {
	switch strings.ToLower(raw) {
	case "debug":
		return slog.LevelDebug
	case "warn":
		return slog.LevelWarn
	case "error":
		return slog.LevelError
	default:
		return slog.LevelInfo
	}
}
