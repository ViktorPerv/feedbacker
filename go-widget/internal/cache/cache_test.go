package cache

import (
	"context"
	"testing"
	"time"
)

func TestSetThenGet(t *testing.T) {
	c := NewMemory(0)
	defer c.Close()

	ctx := context.Background()
	c.Set(ctx, "wall:acme", []byte("payload"), time.Minute)

	got, ok := c.Get(ctx, "wall:acme")
	if !ok {
		t.Fatal("expected a cache hit")
	}

	if string(got) != "payload" {
		t.Errorf("value = %q, want payload", string(got))
	}
}

func TestGetMissesUnknownKeys(t *testing.T) {
	c := NewMemory(0)
	defer c.Close()

	if _, ok := c.Get(context.Background(), "nope"); ok {
		t.Error("expected a miss for an unknown key")
	}
}

func TestEntriesExpire(t *testing.T) {
	c := NewMemory(0)
	defer c.Close()

	ctx := context.Background()
	c.Set(ctx, "short", []byte("value"), 20*time.Millisecond)

	time.Sleep(40 * time.Millisecond)

	if _, ok := c.Get(ctx, "short"); ok {
		t.Error("expected the entry to have expired")
	}
}

func TestNonPositiveTTLIsIgnored(t *testing.T) {
	c := NewMemory(0)
	defer c.Close()

	ctx := context.Background()
	c.Set(ctx, "forever", []byte("value"), 0)

	if _, ok := c.Get(ctx, "forever"); ok {
		t.Error("a zero TTL should not store anything")
	}

	if c.Len() != 0 {
		t.Errorf("cache holds %d entries, want 0", c.Len())
	}
}

func TestGetReturnsACopy(t *testing.T) {
	c := NewMemory(0)
	defer c.Close()

	ctx := context.Background()
	c.Set(ctx, "key", []byte("original"), time.Minute)

	first, _ := c.Get(ctx, "key")
	first[0] = 'X'

	second, _ := c.Get(ctx, "key")
	if string(second) != "original" {
		t.Errorf("cached value was mutated through the returned slice: %q", string(second))
	}
}

func TestSetCopiesTheValue(t *testing.T) {
	c := NewMemory(0)
	defer c.Close()

	ctx := context.Background()
	value := []byte("original")
	c.Set(ctx, "key", value, time.Minute)

	value[0] = 'X'

	got, _ := c.Get(ctx, "key")
	if string(got) != "original" {
		t.Errorf("cached value changed when the caller mutated the source: %q", string(got))
	}
}

func TestForget(t *testing.T) {
	c := NewMemory(0)
	defer c.Close()

	ctx := context.Background()
	c.Set(ctx, "key", []byte("value"), time.Minute)
	c.Forget(ctx, "key")

	if _, ok := c.Get(ctx, "key"); ok {
		t.Error("expected the key to be gone")
	}
}

func TestSweeperRemovesStaleEntries(t *testing.T) {
	c := NewMemory(10 * time.Millisecond)
	defer c.Close()

	c.Set(context.Background(), "stale", []byte("value"), 10*time.Millisecond)

	// Wait long enough for both the entry and the sweep to pass.
	time.Sleep(80 * time.Millisecond)

	if c.Len() != 0 {
		t.Errorf("cache holds %d entries, want the sweeper to have cleared them", c.Len())
	}
}

func TestCloseIsIdempotent(t *testing.T) {
	c := NewMemory(10 * time.Millisecond)
	c.Close()
	c.Close()
}
