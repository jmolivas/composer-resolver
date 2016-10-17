# Composer Resolver

Composer Resolver is a simple, dockerized application that provides a
very simple API that accepts a `composer.json` and returns a job id
with which you can later fetch the resulting `composer.lock` file.

## Why would one need this?

Composer needs a lot of memory. Some applications out there rely
on `composer update` commands directly on devices (webservers,
embedded systems etc.) that do not provide the necessary
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

You can verify for correct creation by checking both, the `201 Created`
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
    "status" "waiting",
    "links": [
        "composerLock": "jobs/5241c3603853e648127910e71ea235b7/composerLock",
        "composerOutput": "jobs/5241c3603853e648127910e71ea235b7/composerOutput",
    ]
}
```

You'll get a `404 Not Found` if the job ID could not be found.

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
    "status" "running",
    "links": [
        "composerLock": "jobs/5241c3603853e648127910e71ea235b7/composerLock",
        "composerOutput": "jobs/5241c3603853e648127910e71ea235b7/composerOutput",
    ]
}
```

Notice that you still get the `202 Accepted` status but the status is
`running`.

As soon as your job has finished, the response will look like this.
The final indicator here is the `200 OK` http status code:

```
HTTP/1.1 200 OK
Content-Type: application/json; charset=UTF-8

{
    "jobId": "5241c3603853e648127910e71ea235b7",
    "status" "finished",
    "links": [
        "composerLock": "jobs/5241c3603853e648127910e71ea235b7/composerLock",
        "composerOutput": "jobs/5241c3603853e648127910e71ea235b7/composerOutput",
    ]
}
```

Done! You can get the `composer.lock` file at the URL indicated in the
`links` section of the response.

A job can also be finished while encountering errors (e.g. a set of 
dependencies that is not resolvable). In this case, you'll get the
`finished_with_errors` status (note that in this case, fetching the
`composerLock` endpoint will result in a `404 Not Found` because
there's obviously no `composer.lock` file that could have been written:

```
HTTP/1.1 200 OK
Content-Type: application/json; charset=UTF-8

{
    "jobId": "5241c3603853e648127910e71ea235b7",
    "status" "finished_with_errors",
    "links": [
        "composerLock": "jobs/5241c3603853e648127910e71ea235b7/composerLock",
        "composerOutput": "jobs/5241c3603853e648127910e71ea235b7/composerOutput",
    ]
}
```


During the resolving process, you can also fetch the complete console
output of Composer itself using the `composerOutput` endpoint.

If you want to inform the users about the approx. waiting time for the
job to be started, just `GET` the index `/` route and you'll be informed
about the state of the cloud like this:

```
HTTP/1.1 200 OK
Content-Type: application/json; charset=UTF-8

{
   "approxWaitingTime": 155
   "approxWaitingTimeHuman": "2 min 35 s",
   "numberOfJobsInQueue": 31,
   "numberOfWorkers": 6
}
```

Also see the "Configure" section on how this is being calculated.

## Update with different options

As we all know, `composer update` does provide different arguments and
options on the command line to influence the outcome of the update.
The Composer Resolver supports a subset of those using the
`Composer-Resolver-Command` HTTP header. Just use it the same way
you would when executing the command on command line. Example:

```
Composer-Resolver-Command: my-vendor/my-package -vvv --profile --no-suggest
```

Note: It does not make sense to support everything the command line does
(e.g. it cannot be interactive so `--no-interaction` is always set and
cannot be modified). The only argument (`packages` to restrict to a list
of packages) is supported. Options are not all available. Here's the list
of what you can use (you can also use the aliases such as `-vvv` for 
`--verbose=3`:

* prefer-source
* prefer-dist
* no-dev
* no-suggest
* prefer-stable
* prefer-lowest
* ansi
* no-ansi
* profile
* verbose


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

### Configure

There are environment variables to configure the way the worker is
working (pun intended):

* `COMPOSER-RESOLVER-JOBS-QUEUE-KEY` - specifies the jobs queue name used for Redis (default `jobs-queue`)
* `COMPOSER-RESOLVER-POLLING-FREQUENCY` - specifies the frequency the workers are polling for new jobs in seconds (default `5`)
* `COMPOSER-RESOLVER-JOBS-TTL` - specifies the TTL for a job in seconds. It will be dropped afterwards. (default `600`)
* `COMPOSER-RESOLVER-JOBS-ATPJ` - specifies the "average time per job" needed to complete in seconds. Used for the current waiting time feature. (default `30`)
* `COMPOSER-RESOLVER-WORKERS` - specifies the number of workers in place. Used for the current waiting time feature. (default `1`)

## Development

Development:

```
$ docker-compose up -d
```

Build:

```
$ docker-compose -f ./docker/docker-compose-production.yml build
$ docker-compose -f ./docker/docker-compose-production.yml push
$ docker-compose -f ./docker/docker-compose-production.yml bundle -o composer-resolver.dab
```
