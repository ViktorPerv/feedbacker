package middleware

import (
	"log/slog"
	"net"
	"net/http"
	"strconv"
	"strings"
	"sync"
	"time"
)

// CORS allows the widget and its tracking calls to be used from any site.
// Pass "*" (the default) or a comma-separated allow-list of origins.
func CORS(allowedOrigins string) func(http.Handler) http.Handler {
	allowedOrigins = strings.TrimSpace(allowedOrigins)
	if allowedOrigins == "" {
		allowedOrigins = "*"
	}

	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			origin := r.Header.Get("Origin")

			switch {
			case allowedOrigins == "*":
				w.Header().Set("Access-Control-Allow-Origin", "*")

			case origin != "" && originAllowed(origin, allowedOrigins):
				w.Header().Set("Access-Control-Allow-Origin", origin)
				w.Header().Add("Vary", "Origin")
			}

			w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
			w.Header().Set("Access-Control-Allow-Headers", "Content-Type")
			w.Header().Set("Access-Control-Max-Age", "86400")

			if r.Method == http.MethodOptions {
				w.WriteHeader(http.StatusNoContent)
				return
			}

			next.ServeHTTP(w, r)
		})
	}
}

func originAllowed(origin, list string) bool {
	for _, candidate := range strings.Split(list, ",") {
		if strings.EqualFold(strings.TrimSpace(candidate), origin) {
			return true
		}
	}

	return false
}

// statusRecorder captures the status code and byte count for the access log.
type statusRecorder struct {
	http.ResponseWriter
	status int
	bytes  int
}

func (r *statusRecorder) WriteHeader(status int) {
	r.status = status
	r.ResponseWriter.WriteHeader(status)
}

func (r *statusRecorder) Write(b []byte) (int, error) {
	if r.status == 0 {
		r.status = http.StatusOK
	}

	n, err := r.ResponseWriter.Write(b)
	r.bytes += n

	return n, err
}

// Logging writes one structured line per request.
func Logging(logger *slog.Logger) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			start := time.Now()
			recorder := &statusRecorder{ResponseWriter: w}

			next.ServeHTTP(recorder, r)

			if recorder.status == 0 {
				recorder.status = http.StatusOK
			}

			logger.Info("request",
				"method", r.Method,
				"path", r.URL.Path,
				"status", recorder.status,
				"bytes", recorder.bytes,
				"duration_ms", time.Since(start).Milliseconds(),
				"ip", ClientIP(r),
			)
		})
	}
}

// Recover turns a panic into a 500 instead of dropping the connection.
func Recover(logger *slog.Logger) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			defer func() {
				if recovered := recover(); recovered != nil {
					logger.Error("panic recovered",
						"path", r.URL.Path,
						"error", recovered,
					)

					w.Header().Set("Content-Type", "application/json")
					w.WriteHeader(http.StatusInternalServerError)
					w.Write([]byte(`{"error":"internal server error"}`))
				}
			}()

			next.ServeHTTP(w, r)
		})
	}
}

// ClientIP resolves the caller, honouring proxy headers.
func ClientIP(r *http.Request) string {
	if forwarded := r.Header.Get("X-Forwarded-For"); forwarded != "" {
		parts := strings.Split(forwarded, ",")
		if candidate := strings.TrimSpace(parts[0]); net.ParseIP(candidate) != nil {
			return candidate
		}
	}

	if real := strings.TrimSpace(r.Header.Get("X-Real-IP")); real != "" {
		if net.ParseIP(real) != nil {
			return real
		}
	}

	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}

	return host
}

// ── Rate limiting ──

type window struct {
	count     int
	expiresAt time.Time
}

// RateLimiter is a fixed-window counter keyed by client IP.
//
// Fixed windows are coarse but cheap and predictable, which is the right
// trade-off for protecting public write endpoints from accidental floods.
type RateLimiter struct {
	mu      sync.Mutex
	windows map[string]window
	limit   int
	period  time.Duration
	stop    chan struct{}
	once    sync.Once
}

// NewRateLimiter allows limit requests per period per key.
func NewRateLimiter(limit int, period time.Duration) *RateLimiter {
	rl := &RateLimiter{
		windows: make(map[string]window),
		limit:   limit,
		period:  period,
		stop:    make(chan struct{}),
	}

	go rl.sweep()

	return rl
}

// Allow reports whether the key may proceed, and how long to wait if not.
func (rl *RateLimiter) Allow(key string) (bool, time.Duration) {
	now := time.Now()

	rl.mu.Lock()
	defer rl.mu.Unlock()

	current, ok := rl.windows[key]
	if !ok || now.After(current.expiresAt) {
		rl.windows[key] = window{count: 1, expiresAt: now.Add(rl.period)}
		return true, 0
	}

	if current.count >= rl.limit {
		return false, time.Until(current.expiresAt)
	}

	current.count++
	rl.windows[key] = current

	return true, 0
}

// Close stops the sweeper. Safe to call more than once.
func (rl *RateLimiter) Close() {
	rl.once.Do(func() { close(rl.stop) })
}

func (rl *RateLimiter) sweep() {
	ticker := time.NewTicker(rl.period)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			now := time.Now()

			rl.mu.Lock()
			for key, w := range rl.windows {
				if now.After(w.expiresAt) {
					delete(rl.windows, key)
				}
			}
			rl.mu.Unlock()

		case <-rl.stop:
			return
		}
	}
}

// Limit applies the limiter, answering 429 with Retry-After when exhausted.
func Limit(limiter *RateLimiter, scope string) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			allowed, retryAfter := limiter.Allow(scope + ":" + ClientIP(r))

			if !allowed {
				seconds := int(retryAfter.Seconds())
				if seconds < 1 {
					seconds = 1
				}

				w.Header().Set("Retry-After", strconv.Itoa(seconds))
				w.Header().Set("Content-Type", "application/json")
				w.WriteHeader(http.StatusTooManyRequests)
				w.Write([]byte(`{"error":"too many requests"}`))

				return
			}

			next.ServeHTTP(w, r)
		})
	}
}
