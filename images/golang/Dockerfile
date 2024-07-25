FROM golang:alpine
# To use service we should use non-alpine image, but let's keep it simple for now

WORKDIR /usr/src/app

# Install PHP so the daemon can run the PHP script
RUN apk add --no-cache php

# pre-copy/cache go.mod for pre-downloading dependencies and only redownloading them in subsequent builds if they change
COPY go.mod go.sum ./
RUN go mod download && go mod verify

COPY daemon.go .
RUN go build -v -o /usr/local/bin/app ./...

CMD ["app"]
