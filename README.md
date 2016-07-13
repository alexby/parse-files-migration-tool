#Parse server migration tool
This is a simple PHP tool to migrate from SAAS Parse backed to self hosted Parse Server.

This is a W.I.P version with very roughs edges 

##Export command
This command will allow you to migrate and existing Parse mongoDB data into your own mongoDB database and

##Delete command
This wipe a given s3 bucket from Parse server data.

##Migrate command

This command will read from a SAS Parse server, upload pictures to a given S3 bucket and export parse data to a given mongo DB

## Getting started:
```bash
composer require Ilius\Parse
```

* fill up src/ParseServerMigration/Config.php.dist constants with your credentials and rename it to Config.php

Try your setup with a simple 1 file migration: 

```bash
php application.php parse:migration:export
```

If everything went fine you can to export you whole database with : 

```bash
php application.php parse:migration:migrate
```

run main command: 

```bash
php application.php parse:migration
```
