create table ai_models
(
  id                 varchar(50)                       not null
    primary key,
  name               varchar(100)                      not null,
  description        varchar(1000)                     null,
  context_length     int                               not null,
  created            date                              not null,
  cost_input         decimal(10, 8) default 0.00000000 not null,
  cost_output        decimal(10, 8) default 0.00000000 not null,
  structured_outputs tinyint(1)                        null,
  constraint name
    unique (name)
);

create table countries
(
  id                char(2)     not null
    primary key,
  name              varchar(50) null,
  domain            varchar(50) null,
  options           text        null,
  defaultlanguageid char(2)     null,
  constraint countries_id_uindex
    unique (id)
);

create table languages
(
  id           char(2)     not null
    primary key,
  name         varchar(50) null,
  translations mediumtext  null,
  constraint languages_id_uindex
    unique (id)
);

create table logins
(
  id        int auto_increment
    primary key,
  userid    int         null,
  tokenhash varchar(60) null,
  lastlogin timestamp   null
);

create table logs
(
  id        int auto_increment
    primary key,
  userid    int                                   null,
  timestamp timestamp default current_timestamp() null,
  level     tinyint                               null,
  ip        varchar(45)                           null,
  info      varchar(255)                          not null,
  constraint logs_id_uindex
    unique (id)
);

create table longtexts
(
  id          char(100)            not null,
  language_id char(2) default 'en' not null,
  content     text                 null,
  primary key (id, language_id)
);

create table questionnaires
(
  id         int auto_increment
    primary key,
  active     smallint   default 0 null comment 'Users are asked to answer questions',
  type       smallint   default 0 null comment '0: standard
1: Bechdel test',
  country_id char(2)              null,
  title      varchar(100)         null,
  public     tinyint(1) default 0 null comment 'Results are publicly available'
);

create table questions
(
  id             int auto_increment
    primary key,
  text           varchar(200)         null,
  active         tinyint(1) default 0 null,
  question_order smallint             null,
  explanation    varchar(200)         null
);

create table questionnaire_questions
(
  questionnaire_id int      not null,
  question_id      int      not null,
  question_order   smallint null,
  primary key (questionnaire_id, question_id),
  constraint quest_questions_questionnaires_id_fk
    foreign key (questionnaire_id) references questionnaires (id)
      on update cascade on delete cascade,
  constraint quest_questions_questions_id_fk
    foreign key (question_id) references questions (id)
      on update cascade
);

create table users
(
  id                   int auto_increment
    primary key,
  email                varchar(254)                          not null,
  firstname            varchar(100)                          null,
  lastname             varchar(100)                          null,
  language             char(2)                               null,
  countryid            char(2)                               null,
  registrationtime     timestamp default current_timestamp() not null,
  passwordhash         varchar(60)                           null,
  passwordrecoveryid   varchar(16)                           null,
  passwordrecoverytime timestamp                             null,
  permission           tinyint   default 0                   null comment '0=helper; 1=admin; 2=moderator',
  lastactive           timestamp default current_timestamp() not null,
  constraint users_email_uindex
    unique (email),
  constraint users_id_uindex
    unique (id),
  constraint users_FK
    foreign key (language) references languages (id)
      on update cascade on delete set null,
  constraint users_FK_country
    foreign key (countryid) references countries (id)
      on update cascade on delete set null
);

create table ai_prompts
(
  id              int auto_increment
    primary key,
  user_id         int           not null,
  function        varchar(20)   null comment 'Used in website function calls',
  model_id        varchar(50)   null,
  user_prompt     varchar(5000) null,
  system_prompt   varchar(5000) null comment 'Openrouter.ai style system instructions',
  response_format varchar(5000) null,
  article_id      int           null,
  constraint web_function
    unique (function),
  constraint ai_prompts_users_id_fk
    foreign key (user_id) references users (id)
      on update cascade
);

create table crashes
(
  id                 int auto_increment
    primary key,
  userid             int                                    null,
  awaitingmoderation tinyint(1) default 1                   null,
  createtime         timestamp  default current_timestamp() not null,
  updatetime         timestamp  default current_timestamp() not null,
  date               date                                   null,
  streamtopuserid    int                                    null,
  streamtoptype      smallint                               null comment '1: edited, 2: article added, 3: placed on top',
  title              varchar(500)                           not null,
  text               varchar(500)                           null,
  countryid          char(2)                                null,
  location           point                                  null,
  latitude           decimal(9, 6)                          null,
  longitude          decimal(9, 6)                          null,
  tree               tinyint(1) default 0                   null,
  trafficjam         tinyint(1) default 0                   null,
  unilateral         tinyint(1)                             null,
  hitrun             tinyint(1) default 0                   null,
  website            varchar(1000)                          null,
  pet                tinyint(1) default 0                   null,
  streamdatetime     timestamp  default current_timestamp() not null,
  constraint posts_id_uindex
    unique (id),
  constraint crashes_countries_id_fk
    foreign key (countryid) references countries (id)
      on update cascade on delete set null,
  constraint posts___fk_user
    foreign key (userid) references users (id)
      on update cascade on delete cascade
);

create table articles
(
  id                 int auto_increment
    primary key,
  crashid            int                                          null,
  userid             int                                          null,
  awaitingmoderation tinyint(1)     default 1                     null,
  createtime         timestamp      default current_timestamp()   null,
  streamdatetime     timestamp      default current_timestamp()   not null,
  publishedtime      timestamp      default '0000-00-00 00:00:00' not null,
  title              varchar(500)                                 not null,
  text               varchar(500)                                 not null,
  alltext            varchar(10000) default ''                    null,
  url                varchar(1000)                                not null,
  urlimage           varchar(1000)                                not null,
  sitename           varchar(200)                                 not null,
  constraint articles_id_uindex
    unique (id),
  constraint articles___fk_crashes
    foreign key (crashid) references crashes (id)
      on update cascade on delete cascade,
  constraint articles___fk_user
    foreign key (userid) references users (id)
      on update cascade on delete cascade
);

create table answers
(
  questionid  int          not null,
  articleid   int          not null,
  answer      tinyint(1)   null,
  explanation varchar(200) null,
  constraint answers_pk
    unique (questionid, articleid),
  constraint answers_articles_id_fk
    foreign key (articleid) references articles (id)
      on update cascade on delete cascade,
  constraint answers_questions_id_fk
    foreign key (questionid) references questions (id)
      on update cascade on delete cascade
);

create index articles__index_crashid
  on articles (crashid);

create fulltext index title
  on articles (title, text);

create index crashes__date_streamdate_index
  on crashes (date, streamdatetime);

create index crashes__index_date
  on crashes (date);

create index crashes__index_streamdatetime
  on crashes (streamdatetime);

create fulltext index title
  on crashes (title, text);

create table crashpersons
(
  id                 int auto_increment
    primary key,
  crashid            int                not null,
  transportationmode smallint default 0 null comment 'unknown: 0, pedestrian: 1, bicycle: 2, scooter: 3, motorcycle: 4, car: 5, taxi: 6, emergencyVehicle: 7, deliveryVan: 8,  tractor: 9,  bus: 10, tram: 11, truck: 12, train: 13, wheelchair: 14, mopedCar: 15, scooter: 16',
  health             smallint           null comment 'unknown: 0, uninjured: 1, injured: 2, dead: 3',
  child              smallint           null,
  underinfluence     tinyint(1)         null,
  hitrun             tinyint(1)         null,
  groupid            int                null,
  constraint crashpersons___fkcrashes
    foreign key (crashid) references crashes (id)
      on update cascade on delete cascade
);

create index crashpersons___fkcrash
  on crashpersons (crashid);

create index crashpersons___fkgroup
  on crashpersons (groupid);

