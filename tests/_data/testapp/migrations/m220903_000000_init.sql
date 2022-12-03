create table if not exists `user` (
	`id` serial primary key,
	`uid` varchar(190) NOT NULL unique, -- Maximum indexable length in innodb with utfmb4
	`name` varchar(250) NOT NULL,
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) engine innodb;
