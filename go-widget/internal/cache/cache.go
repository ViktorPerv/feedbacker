package cache

import (
	"context"
	"sync"
	"time"
)

// Cache is the small surface the handlers need.
//
// The service ships an in-process implementation: for a single instance it is
// faster than a network hop to Redis and needs no extra dependency. A shared
// cache only becomes necessary when the service is scaled horizontally, at
// which point this interface is the seam to swap.
type Cache interface {
	// Get returns the cached bytes and whether they were present and fresh.
	Get(ctx context.Context, key string) ([]byte, bool)

	// Set stores bytes under key for ttl.
	Set(ctx context.Context, key string, value []byte, ttl time.Duration)

	// Forget drops a key.
	Forget(ctx context.Context, key string)
}

type entry struct {
	value     []byte
	expiresAt time.Time
}

// Memory is a TTL cache guarded by a mutex.
//
// It is deliberately simple: a map plus lazy expiry on read, with a periodic
// sweep so keys that are never read again do not accumulate.
type Memory struct {
	mu      sync.RWMutex
	entries map[string]entry
	stop    chan struct{}
	once    sync.Once
}

// NewMemory starts a cache and its background sweeper.
func NewMemory(sweepEvery time.Duration) *Memory {
	c := &Memory{
		entries: make(map[string]entry),
		stop:    make(chan struct{}),
	}

	if sweepEvery > 0 {
		go c.sweep(sweepEvery)
	}

	return c
}

func (c *Memory) Get(_ context.Context, key string) ([]byte, bool) {
	c.mu.RLock()
	found, ok := c.entries[key]
	c.mu.RUnlock()

	if !ok {
		return nil, false
	}

	if time.Now().After(found.expiresAt) {
		c.Forget(context.Background(), key)
		return nil, false
	}

	// Return a copy so a caller cannot mutate the cached slice.
	out := make([]byte, len(found.value))
	copy(out, found.value)

	return out, true
}

func (c *Memory) Set(_ context.Context, key string, value []byte, ttl time.Duration) {
	if ttl <= 0 {
		return
	}

	stored := make([]byte, len(value))
	copy(stored, value)

	c.mu.Lock()
	c.entries[key] = entry{value: stored, expiresAt: time.Now().Add(ttl)}
	c.mu.Unlock()
}

func (c *Memory) Forget(_ context.Context, key string) {
	c.mu.Lock()
	delete(c.entries, key)
	c.mu.Unlock()
}

// Len reports how many entries are held, expired or not. Used by tests.
func (c *Memory) Len() int {
	c.mu.RLock()
	defer c.mu.RUnlock()

	return len(c.entries)
}

// Close stops the sweeper. Safe to call more than once.
func (c *Memory) Close() {
	c.once.Do(func() { close(c.stop) })
}

func (c *Memory) sweep(every time.Duration) {
	ticker := time.NewTicker(every)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			now := time.Now()

			c.mu.Lock()
			for key, found := range c.entries {
				if now.After(found.expiresAt) {
					delete(c.entries, key)
				}
			}
			c.mu.Unlock()

		case <-c.stop:
			return
		}
	}
}
