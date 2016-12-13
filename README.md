# Composer Resolver

[![](https://img.shields.io/travis/Toflar/composer-resolver/master.svg?style=flat-square)](https://travis-ci.org/Toflar/composer-resolver/)
[![](https://img.shields.io/coveralls/Toflar/composer-resolver/master.svg?style=flat-square)](https://coveralls.io/github/Toflar/composer-resolver)

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

If there are too many jobs on the queue already, you will get a
`503 Service Unavailable` response. Also see the configuration setting
`COMPOSER_RESOLVER_JOBS_MAX_FACTOR`.

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

To delete a job, simply run a `DELETE` request to
`/jobs/5241c3603853e648127910e71ea235b7`.

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

The Composer Resolver will validate for a `{"config": {"platform": {}}`
definition in the `composer.json` to be present, as this is a requirement.
Otherwise the Composer Resolver would always resolve with its own available
platform which is never what you want to have.
But you can do even more. You can tell it, which packages you already
have installed and thus should be considered for the dependency resolving
process but not make it into the `composer.lock` (similar as the platform
dependencies) by adding it to the `extra` section of your `composer.json`
like this:

```json
{
     "name": "local/website",
     "description": "A local website project",
     "type": "project",
     "require": {
         "stuff/i-need": "^1.0"
     },
     "extra": {
         "composer-resolver": {
              "installed-repository": {
                  "stuff/i-have": "1.0.18"
              }
         }
     }
}

```


## Does it just run composer update?

No, `composer update` would also download the dependencies which is not
the purpose of the Composer Resolver. The only task of the Composer
Resolver is to resolve a given `composer.json` into a valid
`composer.lock` file. Downloading the dependencies would make the tasks
run longer for no reason. That's why Composer Resolver provides its own
installer that does something like a `composer update --dry-run` while
still writing the `composer.lock` file.

## Run in production

Make use of the Docker 1.12+ features and just deploy the service using the bundle
`composer-resolver.dab` and the Docker built-in orchestration features.
As the "Docker Stacks and Distributed Application Bundles" are still an
experimental feature of Docker, make sure you're running the latest beta
of Docker itself. Be prepared for changes!

Note that the way workers work in this project is that they poll every
n seconds and when they found a job, they run that job and then terminate
themselves. This ensures we have no memory leaks and memory is freed after
every run of every worker. The Docker 1.12+ swarm mode feature is perfect
for that use case because if the worker container is terminated, Docker
will take care and spawn a new one so you always have workers running.
It might be tedious during development though that's why you can configure
this behaviour using the `COMPOSER_RESOLVER_TERMINATE_AFTER_RUN` environment
variable on the `worker` containers. See "Configure" for more details.

### Deploy

[Create a swarm](https://docs.docker.com/engine/reference/commandline/swarm_init/) first.

After you created the swarm, create a volume service. It will be used
for the Composer cache so all the worker containers can reuse the same
composer cache:

```
$ docker volume create --name composer-cache
```
 
Obviously you can create a volume of any kind of driver you prefer. Just
make sure it is named `composer-cache` as this is the default settings
for the bundle which we'll create now. You can of course just set a
different name and adjust the environment variable `COMPOSER_CACHE_DIR`
for your worker service.

Let's create the bundle:

```
$ docker-compose -f ./docker/docker-compose-production.yml bundle -o composer-resolver.dab
```

Now you can just deploy all the needed services like this:

```
$ docker deploy composer-resolver
```

Of course you don't have to use the DAB files, you can create the services
manually if you like.

Now, we have to link the `composer-cache` volume to our worker service:

```
$ docker service update --mount-add type=volume,source=composer-cache,destination=/var/composer-cache composer-resolver_worker
```

That's it, your Composer resolver swarm is running. If and how you make
it accessible from the outside world, however, depends on your integration
and I'll leave that to you.
Check out the configuration options that are following now.

### Configure

There are environment variables to configure the way the resolver is
working:

* On the `web` container/service:
    * `COMPOSER_RESOLVER_JOBS_QUEUE_KEY` - specifies the jobs queue name used for Redis (default `jobs-queue`)
    * `COMPOSER_RESOLVER_JOBS_TTL` - specifies the TTL for a job in seconds. It will be dropped afterwards. (default `600`)
    * `COMPOSER_RESOLVER_JOBS_MAX_FACTOR` - used to limit the number of jobs. It is a factor and relates to `COMPOSER_RESOLVER_WORKERS`. If you have `10` workers and specified a factor of `20` the maximum allowed jobs on the queue equals `200` (`10 * 20`). (default `20`)
    * `COMPOSER_RESOLVER_JOBS_ATPJ` - specifies the "average time per job" needed to complete in seconds. Used for the current waiting time feature. (default `60`)
    * `COMPOSER_RESOLVER_WORKERS` - specifies the number of workers in place. Used for the current waiting time feature. (default `1`)

* On the `worker` container/service:
    * `COMPOSER_RESOLVER_JOBS_QUEUE_KEY` - specifies the jobs queue name used for Redis (default `jobs-queue`)
    * `COMPOSER_RESOLVER_JOBS_TTL` - specifies the TTL for a job in seconds. It will be dropped afterwards. (default `600`)
    * `COMPOSER_RESOLVER_POLLING_FREQUENCY` - specifies the frequency the workers are polling for new jobs in seconds (default `1`, be careful here and adjust when you scale. Having 500 workers that are all polling every second doesn't sound like a good plan!)
    * `COMPOSER_RESOLVER_TERMINATE_AFTER_RUN` - defines whether the worker process is killed after run (default `1` aka `true`)
    * `COMPOSER_RESOLVER_JOBS_ATPJ` - specifies the "average time per job" needed to complete in seconds. Optional, used for the default setting of `COMPOSER_RESOLVER_JOBS_SECONDS_TO_WAIT_BEFORE_RETRY`. (default `60`)
    * `COMPOSER_RESOLVER_JOBS_MAX_RETRIES_PER_JOB` - if a job fails (important: failing does not mean resolving is not possible, failing means the process fails e.g. due to memory overflow) the resolver will wait for `COMPOSER_RESOLVER_JOBS_SECONDS_TO_WAIT_BEFORE_RETRY` seconds and then try again for the configured number of times (default `5`)
    * `COMPOSER_RESOLVER_JOBS_SECONDS_TO_WAIT_BEFORE_RETRY` - see description of `COMPOSER_RESOLVER_JOBS_MAX_RETRIES_PER_JOB` (default `2 * COMPOSER_RESOLVER_JOBS_ATPJ`)

### Manage / Scale

You can easily scale the service (most likely the workers) like this:

```
$ docker service scale composer-resolver_worker=40
```

Remember to update the environment variables depending on what you scale.
If you scale the workers, you likely want to inform the web service about
the number of workers so that the information about the waiting time is
more accurate:

```
$ docker service update --env-add COMPOSER_RESOLVER_WORKERS=40 composer-resolver_web
```

### Configure swap

Composer requires a lot of RAM which is the initial purpose of this project.
As you'll scale the workers, it will very likely happen that your available
memory is not sufficient at times which why you have to monitor the resolver
closely. Moreover, you should make sure you configure enough swap memory.
Note that swap memory should be used carefully as it can cause HDDs to
suffer from it.

Note: These commands are here to illustrate how it's done on Ubuntu.

To check if you have swap memory available, execute:

```
$ free -h
```

If there's no swap available, create a swap file like this:

```
$ sudo fallocate -l 4G /swapfile
$ sudo chmod 600 /swapfile
$ sudo mkswap /swapfile
$ sudo swapon /swapfile
```

Make sure you choose an appropriate file size.
Also make sure that the swap file is recreated when you reboot (commands
include making a back up and removing it again in case anything goes
wrong):

```
$ sudo cp /etc/fstab /etc/fstab.bak
$ echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
$ sudo rm /etc/fstab.bak
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
```
