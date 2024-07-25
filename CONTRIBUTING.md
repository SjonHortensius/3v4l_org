# Contributing

Thank you for your interest in contributing to this project! Here are some guidelines to get you started.

## Pre-requisites

The easiest way to get started is to use [Docker](https://www.docker.com/) with [Docker Compose](https://docs.docker.com/compose/).
This project has a `compose.yaml` file which will set up a development environment for you.

## Setup

1. Clone this repository
2. Clone the [Basic_Framework](https://github.com/SjonHortensius/Basic_Framework) repository
  - TODO: Simplify this step
3. Copy `config.example.ini` to `config.ini`
4. Run `docker compose up`
5. Navigate to `phpshell.localhost`

## How does it work

### Live mode

For live evaluation we use [`php-wasm`](https://github.com/seanmorris/php-wasm).

### Daemon

When a user press the `eval();` button we will:
1. Persist the code in the database (in the `input` and `input_src` tables)
2. Schedule the code to be executed by the daemon
3. The daemon will execute the code and store the output in the database (in the `result` and `output` tables)
4. The user will be redirected to the output page

## Troubleshooting

### `config.ini`

This file is useful to configure the application.

### `daemon.service`

This file is useful to configure the daemon.
In the compose file we run the daemon directly so we do not need the service.
