Example of docker container initialization log, how `php app migrate/wait` in `entrypoint.sh` affects the startup.
```
2022-12-03 18:50:44 umvc-php-1      | initializing the container
2022-12-03 18:50:44 umvc-php-1      | ==========================
2022-12-03 18:50:45 umvc-php-1      | Loading composer repositories with package information
2022-12-03 18:50:45 umvc-php-1      | Info from https://repo.packagist.org: #StandWithUkraine
2022-12-03 18:50:47 umvc-php-1      | Updating dependencies
2022-12-03 18:50:47 umvc-php-1      | Nothing to modify in lock file
2022-12-03 18:50:47 umvc-php-1      | Installing dependencies from lock file (including require-dev)
2022-12-03 18:50:47 umvc-php-1      | Nothing to install, update or remove
2022-12-03 18:50:47 umvc-php-1      | Package simplesamlphp/simplesamlphp-module-authfacebook is abandoned, you should avoid using it. No replacement was suggested.
2022-12-03 18:50:47 umvc-php-1      | Package simplesamlphp/simplesamlphp-module-authwindowslive is abandoned, you should avoid using it. No replacement was suggested.
2022-12-03 18:50:47 umvc-php-1      | Package simplesamlphp/simplesamlphp-module-oauth is abandoned, you should avoid using it. No replacement was suggested.
2022-12-03 18:50:47 umvc-php-1      | Package simplesamlphp/simplesamlphp-module-riak is abandoned, you should avoid using it. No replacement was suggested.
2022-12-03 18:50:47 umvc-php-1      | Package twig/extensions is abandoned, you should avoid using it. No replacement was suggested.
2022-12-03 18:50:47 umvc-php-1      | Generating autoload files
2022-12-03 18:50:49 umvc-php-1      | 64 packages you are using are looking for funding.
2022-12-03 18:50:49 umvc-php-1      | Use the `composer fund` command to find out more!
2022-12-03 18:50:49 umvc-php-1      | No security vulnerability advisories found
2022-12-03 18:50:49 umvc-php-1      | Waiting for database container to be ready...
2022-12-03 18:50:49 umvc-php-1      | Trying to connect...
2022-12-03 18:50:49 umvc-php-1      | Connection failed
2022-12-03 18:50:51 umvc-php-1      | Trying to connect...
2022-12-03 18:50:51 umvc-php-1      | Connection failed
2022-12-03 18:50:53 umvc-php-1      | Trying to connect...
2022-12-03 18:50:53 umvc-php-1      | Connection failed
2022-12-03 18:50:55 umvc-php-1      | Trying to connect...
2022-12-03 18:50:55 umvc-php-1      | Connection failed
2022-12-03 18:50:44 umvc-test-db-1  | [Entrypoint] MySQL Docker Image 8.0.31-1.2.10-server
2022-12-03 18:50:44 umvc-test-db-1  | [Entrypoint] Initializing database
2022-12-03 18:50:44 umvc-test-db-1  | 2022-12-03T17:50:44.374219Z 0 [Warning] [MY-011068] [Server] The syntax '--skip-host-cache' is deprecated and will be removed in a future release. Please use SET GLOBAL host_cache_size=0 instead.
2022-12-03 18:50:44 umvc-test-db-1  | 2022-12-03T17:50:44.374287Z 0 [Warning] [MY-010918] [Server] 'default_authentication_plugin' is deprecated and will be removed in a future release. Please use authentication_policy instead.
2022-12-03 18:50:44 umvc-test-db-1  | 2022-12-03T17:50:44.374307Z 0 [System] [MY-013169] [Server] /usr/sbin/mysqld (mysqld 8.0.31) initializing of server in progress as process 17
2022-12-03 18:50:44 umvc-test-db-1  | 2022-12-03T17:50:44.383487Z 1 [System] [MY-013576] [InnoDB] InnoDB initialization has started.
2022-12-03 18:50:44 umvc-test-db-1  | 2022-12-03T17:50:44.903832Z 1 [System] [MY-013577] [InnoDB] InnoDB initialization has ended.
2022-12-03 18:50:46 umvc-test-db-1  | 2022-12-03T17:50:46.197198Z 6 [Warning] [MY-010453] [Server] root@localhost is created with an empty password ! Please consider switching off the --initialize-insecure option.
2022-12-03 18:50:57 umvc-php-1      | Trying to connect...
2022-12-03 18:50:57 umvc-php-1      | Connection failed
2022-12-03 18:50:57 umvc-test-db-1  | [Entrypoint] Database initialized
2022-12-03 18:50:57 umvc-test-db-1  | 2022-12-03T17:50:57.872025Z 0 [Warning] [MY-011068] [Server] The syntax '--skip-host-cache' is deprecated and will be removed in a future release. Please use SET GLOBAL host_cache_size=0 instead.
2022-12-03 18:50:57 umvc-test-db-1  | 2022-12-03T17:50:57.873474Z 0 [Warning] [MY-010918] [Server] 'default_authentication_plugin' is deprecated and will be removed in a future release. Please use authentication_policy instead.
2022-12-03 18:50:57 umvc-test-db-1  | 2022-12-03T17:50:57.873497Z 0 [System] [MY-010116] [Server] /usr/sbin/mysqld (mysqld 8.0.31) starting as process 66
2022-12-03 18:50:57 umvc-test-db-1  | 2022-12-03T17:50:57.891407Z 1 [System] [MY-013576] [InnoDB] InnoDB initialization has started.
2022-12-03 18:50:58 umvc-test-db-1  | 2022-12-03T17:50:58.065184Z 1 [System] [MY-013577] [InnoDB] InnoDB initialization has ended.
2022-12-03 18:50:58 umvc-test-db-1  | 2022-12-03T17:50:58.348904Z 0 [Warning] [MY-010068] [Server] CA certificate ca.pem is self signed.
2022-12-03 18:50:58 umvc-test-db-1  | 2022-12-03T17:50:58.348947Z 0 [System] [MY-013602] [Server] Channel mysql_main configured to support TLS. Encrypted connections are now supported for this channel.
2022-12-03 18:50:58 umvc-test-db-1  | 2022-12-03T17:50:58.369063Z 0 [System] [MY-011323] [Server] X Plugin ready for connections. Socket: /var/run/mysqld/mysqlx.sock
2022-12-03 18:50:58 umvc-test-db-1  | 2022-12-03T17:50:58.369114Z 0 [System] [MY-010931] [Server] /usr/sbin/mysqld: ready for connections. Version: '8.0.31'  socket: '/var/lib/mysql/mysql.sock'  port: 0  MySQL Community Server - GPL.
2022-12-03 18:50:58 umvc-test-db-1  | Warning: Unable to load '/usr/share/zoneinfo/iso3166.tab' as time zone. Skipping it.
2022-12-03 18:50:58 umvc-test-db-1  | Warning: Unable to load '/usr/share/zoneinfo/leapseconds' as time zone. Skipping it.
2022-12-03 18:50:59 umvc-php-1      | Trying to connect...
2022-12-03 18:50:59 umvc-php-1      | Connection failed
2022-12-03 18:50:59 umvc-test-db-1  | Warning: Unable to load '/usr/share/zoneinfo/tzdata.zi' as time zone. Skipping it.
2022-12-03 18:50:59 umvc-test-db-1  | Warning: Unable to load '/usr/share/zoneinfo/zone.tab' as time zone. Skipping it.
2022-12-03 18:50:59 umvc-test-db-1  | Warning: Unable to load '/usr/share/zoneinfo/zone1970.tab' as time zone. Skipping it.
2022-12-03 18:51:00 umvc-test-db-1  |
2022-12-03 18:51:00 umvc-test-db-1  | [Entrypoint] ignoring /docker-entrypoint-initdb.d/*
2022-12-03 18:51:00 umvc-test-db-1  |
2022-12-03 18:51:00 umvc-test-db-1  | 2022-12-03T17:51:00.044822Z 14 [System] [MY-013172] [Server] Received SHUTDOWN from user root. Shutting down mysqld (Version: 8.0.31).
2022-12-03 18:51:01 umvc-test-db-1  | 2022-12-03T17:51:01.079909Z 0 [System] [MY-010910] [Server] /usr/sbin/mysqld: Shutdown complete (mysqld 8.0.31)  MySQL Community Server - GPL.
2022-12-03 18:51:01 umvc-php-1      | Trying to connect...
2022-12-03 18:51:01 umvc-php-1      | Connection failed
2022-12-03 18:51:02 umvc-test-db-1  | [Entrypoint] Server shut down
2022-12-03 18:51:02 umvc-test-db-1  |
2022-12-03 18:51:02 umvc-test-db-1  | [Entrypoint] MySQL init process done. Ready for start up.
2022-12-03 18:51:02 umvc-test-db-1  |
2022-12-03 18:51:02 umvc-test-db-1  | [Entrypoint] Starting MySQL 8.0.31-1.2.10-server
2022-12-03 18:51:02 umvc-test-db-1  | 2022-12-03T17:51:02.258230Z 0 [Warning] [MY-011068] [Server] The syntax '--skip-host-cache' is deprecated and will be removed in a future release. Please use SET GLOBAL host_cache_size=0 instead.
2022-12-03 18:51:02 umvc-test-db-1  | 2022-12-03T17:51:02.259193Z 0 [Warning] [MY-010918] [Server] 'default_authentication_plugin' is deprecated and will be removed in a future release. Please use authentication_policy instead.
2022-12-03 18:51:02 umvc-test-db-1  | 2022-12-03T17:51:02.259219Z 0 [System] [MY-010116] [Server] /usr/sbin/mysqld (mysqld 8.0.31) starting as process 1
2022-12-03 18:51:02 umvc-test-db-1  | 2022-12-03T17:51:02.265355Z 1 [System] [MY-013576] [InnoDB] InnoDB initialization has started.
2022-12-03 18:51:02 umvc-test-db-1  | 2022-12-03T17:51:02.384648Z 1 [System] [MY-013577] [InnoDB] InnoDB initialization has ended.
2022-12-03 18:51:02 umvc-test-db-1  | 2022-12-03T17:51:02.634322Z 0 [Warning] [MY-010068] [Server] CA certificate ca.pem is self signed.
2022-12-03 18:51:02 umvc-test-db-1  | 2022-12-03T17:51:02.634427Z 0 [System] [MY-013602] [Server] Channel mysql_main configured to support TLS. Encrypted connections are now supported for this channel.
2022-12-03 18:51:02 umvc-test-db-1  | 2022-12-03T17:51:02.658248Z 0 [System] [MY-011323] [Server] X Plugin ready for connections. Bind-address: '::' port: 33060, socket: /var/run/mysqld/mysqlx.sock
2022-12-03 18:51:02 umvc-test-db-1  | 2022-12-03T17:51:02.658314Z 0 [System] [MY-010931] [Server] /usr/sbin/mysqld: ready for connections. Version: '8.0.31'  socket: '/var/lib/mysql/mysql.sock'  port: 3306  MySQL Community Server - GPL.
2022-12-03 18:51:03 umvc-php-1      | Trying to connect...
2022-12-03 18:51:03 umvc-php-1      | Connected
2022-12-03 18:51:03 umvc-php-1      | Migrating database
2022-12-03 18:51:03 umvc-php-1      | The migrate command keeps database changes in sync with source code.
2022-12-03 18:51:03 umvc-php-1      | Run `php app migrate help` for more details.
2022-12-03 18:51:03 umvc-php-1      |
2022-12-03 18:51:03 umvc-php-1      | Creating `migration` table...
2022-12-03 18:51:03 umvc-php-1      | There are 2 new updates:
2022-12-03 18:51:03 umvc-php-1      |   - /app/tests/_data/testapp/migrations/m220903_000000_init.sql
2022-12-03 18:51:03 umvc-php-1      |   - /app/tests/_data/testapp/migrations/m220915_000000_course.sql
2022-12-03 18:51:04 umvc-php-1      |
2022-12-03 18:51:04 umvc-php-1      | 2 migrations were applied.
2022-12-03 18:51:04 umvc-php-1      | Starting apache
2022-12-03 18:51:04 umvc-php-1      | ---------------
2022-12-03 18:51:04 umvc-php-1      | Enabling module rewrite.
2022-12-03 18:51:04 umvc-php-1      | To activate the new configuration, you need to run:
2022-12-03 18:51:04 umvc-php-1      |   service apache2 restart
2022-12-03 18:51:04 umvc-php-1      | [Sat Dec 03 18:51:04.233312 2022] [mpm_prefork:notice] [pid 1] AH00163: Apache/2.4.38 (Debian) PHP/7.4.33 configured -- resuming normal operations
2022-12-03 18:51:04 umvc-php-1      | [Sat Dec 03 18:51:04.233362 2022] [core:notice] [pid 1] AH00094: Command line: 'apache2 -D FOREGROUND'
```