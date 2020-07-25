create or replace schema hetongeluk collate utf8mb4_general_ci;

create or replace table languages
(
    id char(2) not null,
    name varchar(50) null,
    translations mediumtext null,
    constraint languages_id_uindex
        unique (id)
);

alter table languages
    add primary key (id);

create or replace table logins
(
    id int auto_increment
        primary key,
    userid int null,
    tokenhash varchar(60) null,
    lastlogin timestamp null
)
    charset=utf8;

create or replace table logs
(
    id int auto_increment,
    userid int null,
    timestamp timestamp default CURRENT_TIMESTAMP null,
    level tinyint null,
    ip varchar(45) charset latin1 null,
    info varchar(255) not null,
    constraint logs_id_uindex
        unique (id)
)
    charset=utf8;

alter table logs
    add primary key (id);

create or replace table options
(
    name varchar(50) not null,
    value varchar(10000) null,
    constraint options_name_uindex
        unique (name)
)
    charset=utf8;

alter table options
    add primary key (name);

create or replace table users
(
    id int auto_increment,
    email varchar(254) charset latin1 not null,
    firstname varchar(100) charset latin1 null,
    lastname varchar(100) charset latin1 null,
    language char(2) null,
    lastactive timestamp default CURRENT_TIMESTAMP not null,
    registrationtime timestamp default CURRENT_TIMESTAMP not null,
    passwordhash varchar(60) null,
    passwordrecoveryid varchar(16) charset latin1 null,
    passwordrecoverytime timestamp null,
    permission tinyint default 0 null,
    constraint users_email_uindex
        unique (email),
    constraint users_id_uindex
        unique (id)
)
    comment 'permission: 0=helper; 1=admin; 2=moderator' charset=utf8;

alter table users
    add primary key (id);

create or replace table accidents
(
    id int auto_increment,
    userid int null,
    awaitingmoderation tinyint(1) default 1 null,
    createtime timestamp default CURRENT_TIMESTAMP not null,
    updatetime timestamp default CURRENT_TIMESTAMP not null,
    streamdatetime timestamp default CURRENT_TIMESTAMP not null,
    streamtopuserid int null,
    streamtoptype smallint null,
    title varchar(500) not null,
    text varchar(500) null,
    date date null,
    location point null,
    latitude decimal(9,6) null,
    longitude decimal(9,6) null,
    tree tinyint(1) default 0 null,
    trafficjam tinyint(1) default 0 null,
    unilateral tinyint(1) null,
    hitrun tinyint(1) default 0 null,
    website varchar(1000) null,
    pet tinyint(1) default 0 null,
    constraint posts_id_uindex
        unique (id),
    constraint posts___fk_user
        foreign key (userid) references users (id)
            on update cascade on delete cascade
)
    comment 'streamtoptype: 1: edited, 2: article added, 3: placed on top' charset=utf8;

create or replace index accidents__date_streamdate_index
    on accidents (date, streamdatetime);

create or replace index accidents__index_date
    on accidents (date);

create or replace index accidents__index_streamdatetime
    on accidents (streamdatetime);

create or replace index accidents__index_title
    on accidents (title);

create or replace fulltext index title
    on accidents (title, text);

alter table accidents
    add primary key (id);

create or replace table accidentpersons
(
    id int auto_increment
        primary key,
    accidentid int not null,
    transportationmode smallint default 0 null,
    health smallint null,
    child smallint null,
    underinfluence tinyint(1) null,
    hitrun tinyint(1) null,
    groupid int null,
    constraint accidentpersons___fkaccident
        foreign key (accidentid) references accidents (id)
            on update cascade on delete cascade
)
    comment 'health: unknown: 0, unharmed: 1, injured: 2, dead: 3 | transportationmode: unknown: 0, pedestrian: 1, bicycle: 2, scooter: 3, motorcycle: 4, car: 5, taxi: 6, emergencyVehicle: 7, deliveryVan: 8,  tractor: 9,  bus: 10, tram: 11, truck: 12, train: 13, wheelchair: 14, mopedCar: 15' charset=utf8;

create or replace index accidentpersons___fkgroup
    on accidentpersons (groupid);

create or replace table articles
(
    id int auto_increment,
    accidentid int null,
    userid int null,
    awaitingmoderation tinyint(1) default 1 null,
    createtime timestamp default CURRENT_TIMESTAMP null,
    streamdatetime timestamp default CURRENT_TIMESTAMP not null,
    publishedtime timestamp default '0000-00-00 00:00:00' not null,
    title varchar(500) not null,
    text varchar(500) not null,
    alltext varchar(10000) default '' null,
    url varchar(1000) not null,
    urlimage varchar(1000) not null,
    sitename varchar(200) not null,
    constraint articles_id_uindex
        unique (id),
    constraint articles___fk_accidents
        foreign key (accidentid) references accidents (id)
            on update cascade on delete cascade,
    constraint articles___fk_user
        foreign key (userid) references users (id)
            on update cascade on delete cascade
)
    charset=utf8;

create or replace index articles__index_accidentid
    on articles (accidentid);

create or replace fulltext index title
    on articles (title, text);

alter table articles
    add primary key (id);

