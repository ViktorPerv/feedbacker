package middleware

import (
	"net/http"
	"net/http/httptest"
	"testing"
	"time"
)

func TestRateLimiterAllowsUpToTheLimit(t *testing.T) {
	rl := NewRateLimiter(3, time.Minute)
	defer rl.Close()

	for i := 0; i < 3; i++ {
		if allowed, _ := rl.Allow("1.2.3.4"); !allowed {
			t.Fatalf("request %d should have been allowed", i+1)
		}
	}

	allowed, retryAfter := rl.Allow("1.2.3.4")
	if allowed {
		t.Fatal("the fourth request should have been rejected")
	}

	if retryAfter <= 0 {
		t.Error("a rejected request should report a retry delay")
	}
}

func TestRateLimiterIsPerKey(t *testing.T) {
	rl := NewRateLimiter(1, time.Minute)
	defer rl.Close()

	if allowed, _ := rl.Allow("view:1.1.1.1"); !allowed {
		t.Fatal("first key should be allowed")
	}
	if allowed, _ := rl.Allow("view:1.1.1.1"); allowed {
		t.Fatal("second call for the same key should be rejected")
	}

	if allowed, _ := rl.Allow("view:2.2.2.2"); !allowed {
		t.Fatal("a different key should have its own budget")
	}
	if allowed, _ := rl.Allow("click:1.1.1.1"); !allowed {
		t.Fatal("a different scope should have its own budget")
	}
}

func TestRateLimiterResetsAfterTheWindow(t *testing.T) {
	rl := NewRateLimiter(1, 20*time.Millisecond)
	defer rl.Close()

	rl.Allow("key")
	if allowed, _ := rl.Allow("key"); allowed {
		t.Fatal("expected the second call to be rejected")
	}

	time.Sleep(40 * time.Millisecond)

	if allowed, _ := rl.Allow("key"); !allowed {
		t.Error("the window should have reset")
	}
}

func TestLimitMiddlewareReturns429(t *testing.T) {
	rl := NewRateLimiter(1, time.Minute)
	defer rl.Close()

	handler := Limit(rl, "view")(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusNoContent)
	}))

	first := httptest.NewRecorder()
	handler.ServeHTTP(first, httptest.NewRequest(http.MethodPost, "/api/track/view", nil))

	if first.Code != http.StatusNoContent {
		t.Fatalf("first request status = %d, want 204", first.Code)
	}

	second := httptest.NewRecorder()
	handler.ServeHTTP(second, httptest.NewRequest(http.MethodPost, "/api/track/view", nil))

	if second.Code != http.StatusTooManyRequests {
		t.Fatalf("second request status = %d, want 429", second.Code)
	}

	if second.Header().Get("Retry-After") == "" {
		t.Error("a 429 should carry a Retry-After header")
	}
}

func TestClientIPPrefersForwardedFor(t *testing.T) {
	request := httptest.NewRequest(http.MethodGet, "/", nil)
	request.RemoteAddr = "10.0.0.1:1234"
	request.Header.Set("X-Forwarded-For", "203.0.113.7, 10.0.0.1")

	if got := ClientIP(request); got != "203.0.113.7" {
		t.Errorf("ClientIP = %q, want 203.0.113.7", got)
	}
}

func TestClientIPFallsBackToRemoteAddr(t *testing.T) {
	request := httptest.NewRequest(http.MethodGet, "/", nil)
	request.RemoteAddr = "192.0.2.5:9999"

	if got := ClientIP(request); got != "192.0.2.5" {
		t.Errorf("ClientIP = %q, want 192.0.2.5", got)
	}
}

func TestClientIPIgnoresMalformedForwardedValues(t *testing.T) {
	request := httptest.NewRequest(http.MethodGet, "/", nil)
	request.RemoteAddr = "192.0.2.5:9999"
	request.Header.Set("X-Forwarded-For", "not-an-ip")

	if got := ClientIP(request); got != "192.0.2.5" {
		t.Errorf("ClientIP = %q, want the connection address", got)
	}
}

func TestCORSWildcard(t *testing.T) {
	handler := CORS("*")(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))

	recorder := httptest.NewRecorder()
	request := httptest.NewRequest(http.MethodGet, "/", nil)
	request.Header.Set("Origin", "https://example.com")
	handler.ServeHTTP(recorder, request)

	if got := recorder.Header().Get("Access-Control-Allow-Origin"); got != "*" {
		t.Errorf("Allow-Origin = %q, want *", got)
	}
}

func TestCORSAllowList(t *testing.T) {
	handler := CORS("https://a.example, https://b.example")(
		http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(http.StatusOK)
		}),
	)

	allowed := httptest.NewRecorder()
	allowedRequest := httptest.NewRequest(http.MethodGet, "/", nil)
	allowedRequest.Header.Set("Origin", "https://b.example")
	handler.ServeHTTP(allowed, allowedRequest)

	if got := allowed.Header().Get("Access-Control-Allow-Origin"); got != "https://b.example" {
		t.Errorf("Allow-Origin = %q, want the matching origin", got)
	}

	denied := httptest.NewRecorder()
	deniedRequest := httptest.NewRequest(http.MethodGet, "/", nil)
	deniedRequest.Header.Set("Origin", "https://evil.example")
	handler.ServeHTTP(denied, deniedRequest)

	if got := denied.Header().Get("Access-Control-Allow-Origin"); got != "" {
		t.Errorf("Allow-Origin = %q, want empty for a disallowed origin", got)
	}
}

func TestCORSHandlesPreflight(t *testing.T) {
	called := false
	handler := CORS("*")(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		called = true
	}))

	recorder := httptest.NewRecorder()
	handler.ServeHTTP(recorder, httptest.NewRequest(http.MethodOptions, "/", nil))

	if recorder.Code != http.StatusNoContent {
		t.Errorf("preflight status = %d, want 204", recorder.Code)
	}

	if called {
		t.Error("preflight should not reach the handler")
	}
}
