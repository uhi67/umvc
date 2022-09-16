create table course (
	`id` serial primary key,
	`title` varchar(255) NOT NULL,
	`teacher_id` bigint UNSIGNED NULL,
	`deadline` date,
	`created_by` bigint UNSIGNED NULL,
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_by` bigint UNSIGNED NULL,
	`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	constraint fk_course_teacher foreign key (teacher_id) references user(id) on delete set null,
	constraint fk_course_created foreign key (created_by) references user(id) on delete restrict,
	constraint fk_course_updated foreign key (updated_by) references user(id) on delete restrict
);
