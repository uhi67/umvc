create table user (
	`id` serial primary key,
	`uid` varchar(255) NOT NULL unique,
	`name` varchar(255) NOT NULL,
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
