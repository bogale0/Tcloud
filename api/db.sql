use storage;
create table files (
    file_id int primary key auto_increment,
    size_t bigint default 0,
    chunk_count int default 0,
    created_at timestamp default current_timestamp
);
create table chunks (
    file_id int not null,
    chunk_id int not null,
    tg_file_id varchar(255) not null,
    tg_msg_id int not null,
    primary key (file_id, chunk_id),
    foreign key (file_id) references files(file_id) on delete cascade
);