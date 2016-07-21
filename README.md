# Composer Resolver

Composer Resolver is a simple, dockerized application that provides a
very simple API that accepts a `composer.json` and returns a job id
with which you can later fetch the resulting `composer.lock` file.

## Why would one need this?

Composer needs a lot of time and a lot of memory. Some applications
out there rely on `composer update` commands directly on devices
(webservers, embedded systems etc.) that do not provide the necessary
resources. Everything except `composer update`, however, is perfectly
possible. That means, they can run composer locally and execute
commands like `composer install` etc. without any problems.
The real problem is the resolving action which is why this project was
simply called "Composer Resolver". 

It comes as a simple Docker container so you can run it wherever you want
to. Be it your own hosted cloud or on your own development environment.
Wherever you want to, just not on the device itself where there are not
enough resources :-)

## How does it work?

You `POST` your `composer.json` to the API endpoint `/jobs`. You'll get
a JSON response like this:

```
HTTP/1.1 201 Created
Location: /jobs/5241c3603853e648127910e71ea235b7
Content-Type: application/json; charset=UTF-8

{
    "jobId": "5241c3603853e648127910e71ea235b7",
    "status" "waiting"
}
```

You can verify for correct creation by checking both, th `201 Created`
http status as well as the `status` field in the content.

You can then `GET` the results or the current state of your job by
sending a `GET` request to `/jobs/5241c3603853e648127910e71ea235b7`.

If the job is still waiting to be processed, you'll get the following
response:

```
HTTP/1.1 202 Accepted
Content-Type: application/json; charset=UTF-8

{
    "jobId": "5241c3603853e648127910e71ea235b7",
    "status" "waiting"
}
```

As long as your job is waiting, you'll get the `202 Accepted` http
status.

Notice that the `status` is still `waiting`.

As soon as your job is being processed but has not yet finished, your
response will look like this:

```
HTTP/1.1 202 OK
Content-Type: application/json; charset=UTF-8

{
    "jobId": "5241c3603853e648127910e71ea235b7",
    "status" "running"
}
```

Notice that you still get the `202 Accepted` status but the status is
`running`.

As soon as your job has finished, you won't get any job information
anymore but the whole `composer.lock` instead. The final indicator here
is the `200 OK` http status code:

```
HTTP/1.1 200 OK
Content-Type: application/json; charset=UTF-8

{
    "_readme": [
        "This file locks the dependencies of your project to a known state",
[...]
```

Done!


## Does it just run composer update?

No, `composer update` would also download the dependencies which is not
the purpose of the Composer Resolver. The only task of the Composer
Resolver is to resolve a given `composer.json` into a valid
`composer.lock` file. Downloading the dependencies would make the tasks
run longer for no reason. That's why Composer Resolver provides it's own
installer that does something like a `composer update --dry-run` while
still writing the `composer.lock` file.

## Run in production

Make use of the Docker 1.12+ features and just deploy the service using the bundle
`composer-resolver.dab` and the Docker built-in orchestration features.

### Deploy

Create a swarm first. Then run

```
$ docker deploy composer-resolver
```

or (`deploy` is just an alias for `stack deploy`)

```
$ docker stack deploy composer-resolver
```

That's it. Make sure you checkout the `web` service to publish the port 80 to
the host machine.

You can easily scale the service (most likely the workers) like this:

```
$ docker service scale composer-resolver_worker=40
```


## Development

Development:

```
$ docker-compose up -d
```

Build:

```
$ docker-compose -f ./docker/docker-compose-production.yml build
$ docker-compose -f ./docker/docker-compose-production.yml push
$ docker-compose -f docker/docker-compose-production.yml bundle -o composer-resolver.dab
```

## Desktop App

Part of this repository is an electron app that runs the the Docker containers
as a service on your localhost and provides a simple interface showing you the
status of Docker, taking a composer.json and returning a composer.lock file.
You don't need this if you want to only run the resolver as - say - cloud solution.
The app related files are not part of the Docker images. I did not split the
projects into two repositories (yet) because of convenience.
