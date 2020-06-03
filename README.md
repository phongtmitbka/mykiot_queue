# Mykiot Elasticsearch

About project:
- The project execute update cache_products table from mysql to ElasticSearch

Package
- Laravel 7.0
- ElasticSearch 7.1.^
- Redis queue

##Install project

#### 1. Clone project
- git clone 

#### 2. Config .env
- cp .env.example .env
- Edit and update .env

#### 3. Update composer
- composer update

#### 4. Config CronTab
- Run: crontab -e and paste
```
* * * * * php /var/www/mykiot-queue/artisan schedule:run 1>> /dev/null 2>&1
```

#### 5. Config Supervisord

```$xslt
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ecatalog/artisan queue:work --sleep=1 --tries=3 --daemon
autostart=true
autorestart=true
numprocs=20
#user=laradock
redirect_stderr=true
```

#### 6. Test
- Check data at ElasticSearch
curl -X GET 'http://localhost:9200/mk_products/_count'
