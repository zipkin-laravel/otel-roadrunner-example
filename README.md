# Opentelemetry + roadrunner example
1. build:
   
	docker compose build php
3. update:
   
	docker compose run php composer update
5. start:
   
	docker compose up -d
7. stop:
   
	docker compose stop

# usage

- `make all`
- `curl localhost`
- browse to [zipkin](http://localhost:9411/zipkin) and search for traces


# Run serviceA
1. cd  service-example/serviceA
2. composer install
3. composer require openzipkin/zipkin
4. Update env
```
HTP_REPTORTER_URL=http://localhost:9411/api/v2/spans
```
6. php artisan serve --port=8003

# Run service B
1. cd  service-example/serviceB
2. composer install
3. composer require openzipkin/zipkin
4. Update env
```
HTP_REPTORTER_URL=http://localhost:9411/api/v2/spans
```
```
serviceUrl=http://127.0.0.1:8003/api/value
```
6. php artisan serve --port=8002

And then, request the serviceA:
 
```
curl http://127.0.0.1:8002/api/getvalues
```

Next, you can view traces that went through the backend via http://localhost:9411
