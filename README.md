# zen-demo-frankenphp

> :warning: **SECURITY WARNING**
>
> This is a demonstration application that intentionally contains security vulnerabilities for educational purposes.
> - **DO NOT** run this in production environment
> - **DO NOT** run without proper protection
> - It is strongly recommended to use [Aikido Zen](https://www.aikido.dev/zen) as a security layer


## setup

`git submodule update --init --recursive`
copy `.env.example` to `.env`

## run

Test with database
`DATABASE_URL=postgres://username:passowrd@localhost:5432/aikido?sslmode=disable`

docker build -t zen-demo-frankenphp-worker:dev .
docker run -p 8080:8080 --env-file .env --name "zen-demo-frankenphp-worker" --rm zen-demo-frankenphp-worker:dev
