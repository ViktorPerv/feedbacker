FROM golang:1.23-alpine AS builder

WORKDIR /build

# go.mod declares a newer toolchain than the base image, so let Go fetch it.
ENV GOTOOLCHAIN=auto

COPY go-widget/go.mod go-widget/go.sum* ./
RUN go mod download

COPY go-widget/ ./

# The widget JavaScript is embedded with go:embed, so the binary is
# self-contained and no static directory is shipped.
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /widget-server ./cmd/server

# ── Runtime ──
FROM alpine:3.20

RUN apk add --no-cache ca-certificates tzdata

COPY --from=builder /widget-server /usr/bin/widget-server

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8080/health || exit 1

CMD ["widget-server"]
