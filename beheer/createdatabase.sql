create schema hetongeluk collate utf8_general_ci;

create table logins
(
  id int auto_increment
    primary key,
  userid int null,
  tokenhash varchar(60) null,
  lastlogin timestamp null
);

create table logs
(
  id int auto_increment,
  userid int null,
  timestamp timestamp default CURRENT_TIMESTAMP null,
  level tinyint null,
  ip varchar(45) charset latin1 null,
  info varchar(255) not null,
  constraint logs_id_uindex
    unique (id)
);

alter table logs
  add primary key (id);

create table options
(
  name varchar(50) not null,
  value varchar(10000) null,
  constraint options_name_uindex
    unique (name)
);

alter table options
  add primary key (name);

create table users
(
  id int auto_increment,
  email varchar(254) charset latin1 not null,
  firstname varchar(100) charset latin1 null,
  lastname varchar(100) charset latin1 null,
  permission tinyint default 0 null,
  lastactive timestamp default CURRENT_TIMESTAMP not null,
  registrationtime timestamp default CURRENT_TIMESTAMP not null,
  passwordhash varchar(60) null,
  passwordrecoveryid varchar(16) charset latin1 null,
  passwordrecoverytime timestamp null,
  constraint users_email_uindex
    unique (email),
  constraint users_id_uindex
    unique (id)
)
  comment 'permission: 0=helper; 1=admin; 2=moderator';

alter table users
  add primary key (id);

create table accidents
(
  id int auto_increment,
  userid int null,
  awaitingmoderation tinyint(1) default 1 null,
  createtime timestamp default CURRENT_TIMESTAMP not null,
  updatetime timestamp default CURRENT_TIMESTAMP not null,
  streamdatetime timestamp default CURRENT_TIMESTAMP not null,
  streamtopuserid int null,
  streamtoptype smallint(6) null,
  date date null,
  text varchar(500) null,
  website varchar(1000) null,
  personsdead int null,
  personsinjured int null,
  pedestrian tinyint(1) default 0 null,
  wheelchair tinyint(1) default 0 null,
  mopedcar tinyint(1) default 0 null,
  motorcycle tinyint(1) default 0 null,
  scooter tinyint(1) default 0 null,
  bicycle tinyint(1) default 0 null,
  tractor tinyint(1) default 0 null,
  taxi tinyint(1) default 0 null,
  emergencyvehicle tinyint(1) default 0 null,
  car tinyint(1) default 0 null,
  truck tinyint(1) default 0 null,
  bus tinyint(1) default 0 null,
  tram tinyint(1) default 0 null,
  deliveryvan tinyint(1) default 0 null,
  train tinyint(1) default 0 null,
  transportationunknown tinyint(1) default 0 null,
  child tinyint(1) default 0 null,
  pet tinyint(1) default 0 null,
  alcohol tinyint(1) default 0 null,
  hitrun tinyint(1) default 0 null,
  tree tinyint(1) default 0 null,
  trafficjam tinyint(1) default 0 null,
  title varchar(500) not null,
  constraint posts_id_uindex
    unique (id),
  constraint posts___fk_user
    foreign key (userid) references users (id)
      on update cascade on delete cascade
)
  comment 'streamtoptype: 1: edited, 2: article added, 3: placed on top';

create index accidents__date_streamdate_index
  on accidents (date, streamdatetime);

create index accidents__index_date
  on accidents (date);

create index accidents__index_streamdatetime
  on accidents (streamdatetime);

create index accidents__index_title
  on accidents (title);

create fulltext index title
  on accidents (title, text);

alter table accidents
  add primary key (id);

create table accidentpersons
(
  id int auto_increment
    primary key,
  accidentid int not null,
  transportationmode smallint(6) default 0 null,
  health smallint(6) null,
  child smallint(6) null,
  underinfluence tinyint(1) null,
  hitrun tinyint(1) null,
  groupid int null,
  constraint accidentpersons___fkaccident
    foreign key (accidentid) references accidents (id)
      on update cascade on delete cascade
);

create index accidentpersons___fkgroup
  on accidentpersons (groupid);

create table articles
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
);

create index articles__index_accidentid
  on articles (accidentid);

create fulltext index title
  on articles (title, text);

alter table articles
  add primary key (id);

