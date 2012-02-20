-- Tables used by the MediaWiki Wikilog extension.
--
-- You should not have to create these tables manually unless you are doing
-- a manual installation. In normal conditions, maintenance/update.php should
-- perform any needed database setup.
--

--
-- Postgresql differences from mysql:
--
-- * There is no IF NOT EXISTS
-- * Sequences and enums are created before the table.
-- * Indexes are created after the table
-- * There is no UNSIGNED. Our ints will have to run out sooner.
-- * The equivalent data type for BINARY and BLOB is BYTEA, which
-- are always variable, but don't have a specified limit. 
-- * However, we should use TIMESTAMP for timestamps, because mediawiki 
-- handles those gracefully.
-- * Postgresql has a BOOL type that doesn't like being handed an int, while
-- mysql's bool type is really a tinyint(1), so we use int2 here. 

--
-- All existing wikilogs and associated metadata.
--
CREATE TABLE /*$wgDBprefix*/wikilog_wikilogs (
  -- Primary key, reference to wikilog front page article.
  wlw_page INTEGER NOT NULL,

  -- Serialized PHP object representing the wikilog description or subtitle.
  wlw_subtitle BYTEA NOT NULL,

  -- Image that provides iconic visual identification of the feed.
  wlw_icon BYTEA NOT NULL,

  -- Image that provides visual identification of the feed.
  wlw_logo BYTEA NOT NULL,

  -- Serialized PHP array of authors.
  wlw_authors BYTEA NOT NULL,

  -- Last time the wikilog (including posts) was updated.
  wlw_updated TIMESTAMP WITHOUT TIME ZONE NOT NULL,

  PRIMARY KEY (wlw_page)

) /*$wgDBTableOptions*/;

CREATE INDEX wlw_updated ON /*$wgDBprefix*/wikilog_wikilogs (wlw_updated);

--
-- All wikilog posts and associated metadata.
--
CREATE TABLE /*$wgDBprefix*/wikilog_posts (
  -- Primary key, reference to wiki article associated with this post.
  wlp_page INTEGER NOT NULL,

  -- Parent wikilog.
  wlp_parent INTEGER NOT NULL,

  -- Post title derived from page(page_title), in order to simplify indexing.
  wlp_title BYTEA NOT NULL,

  -- Either if the post was published or not.
  wlp_publish INT2 NOT NULL DEFAULT 0,

  -- If wlp_publish = TRUE, this is the date that the post was published,
  -- otherwise, it is the date of the last draft revision (for sorting).
  wlp_pubdate TIMESTAMP WITHOUT TIME ZONE NOT NULL,

  -- Last time the post was updated.
  wlp_updated TIMESTAMP WITHOUT TIME ZONE NOT NULL,

  -- Serialized PHP array of authors.
  wlp_authors BYTEA NOT NULL,

  -- Serialized PHP array of tags.
  wlp_tags BYTEA NOT NULL,

  -- Cached number of comments.
  wlp_num_comments INTEGER,

  PRIMARY KEY (wlp_page)

) /*$wgDBTableOptions*/;

CREATE INDEX wlp_parent ON /*$wgDBprefix*/wikilog_posts (wlp_parent);
CREATE INDEX wlp_title ON /*$wgDBprefix*/wikilog_posts (wlp_title);
CREATE INDEX wlp_pubdate ON /*$wgDBprefix*/wikilog_posts (wlp_pubdate);
CREATE INDEX wlp_updated ON /*$wgDBprefix*/wikilog_posts (wlp_updated);


--
-- Authors of each post.
--
CREATE TABLE /*$wgDBprefix*/wikilog_authors (
  -- Reference to post wiki article which this author is associated to.
  wla_page INTEGER NOT NULL,

  -- ID of the author of the post.
  wla_author INTEGER NOT NULL,

  -- Name of the author of the post.
  wla_author_text BYTEA NOT NULL,

  PRIMARY KEY (wla_page, wla_author_text)

) /*$wgDBTableOptions*/;

CREATE INDEX wla_page ON /*$wgDBprefix*/wikilog_authors (wla_page);
CREATE INDEX wla_author ON /*$wgDBprefix*/wikilog_authors (wla_author);
CREATE INDEX wla_author_text ON /*$wgDBprefix*/wikilog_authors (wla_author_text);


--
-- Tags associated with each post.
--
CREATE TABLE /*$wgDBprefix*/wikilog_tags (
  -- Reference to post wiki article which this tag is associated to.
  wlt_page INTEGER,

  -- Tag associated with the post.
  wlt_tag BYTEA NOT NULL,

  PRIMARY KEY (wlt_page, wlt_tag)

) /*$wgDBTableOptions*/;

CREATE INDEX wlt_tag ON /*$wgDBprefix*/wikilog_tags (wlt_tag);

--
-- Post comments.
--
CREATE TYPE state AS ENUM (    
       'OK',               -- OK, comment is visible
       'PENDING',          -- Comment is pending moderation
       'DELETED'           -- Comment was deleted
       );


CREATE SEQUENCE wikilog_comments_wlc_id;

CREATE TABLE /*$wgDBprefix*/wikilog_comments (
  -- Unique comment identifier, across the whole wiki.
  wlc_id INTEGER NOT NULL DEFAULT nextval('wikilog_comments_wlc_id'),

  -- Parent comment, for threaded discussion. NULL for top-level comments.
  wlc_parent INTEGER,

  -- Thread history, used for sorting. An array of wlc_id values of all parent
  -- comments up to and including the current comment. Each id is padded with
  -- zeros to six digits ("000000") and joined with slashes ("/").
  wlc_thread BYTEA NOT NULL DEFAULT '',

  -- Reference to post wiki article which this comment is associated to.
  wlc_post INTEGER NOT NULL,

  -- ID of the author of the comment, if a registered user.
  wlc_user INTEGER NOT NULL,

  -- Name of the author of the comment.
  wlc_user_text BYTEA NOT NULL,

  -- Name used for anonymous (not logged in) posters.
  wlc_anon_name BYTEA,

  -- Comment status. For hidden or deleted comments, a placeholder is left
  -- with some description about what happened to the comment.
  wlc_status state NOT NULL DEFAULT 'OK',

  -- Date and time the comment was first posted.
  wlc_timestamp TIMESTAMP WITHOUT TIME ZONE NOT NULL,

  -- Date and time the comment was edited for the last time.
  wlc_updated TIMESTAMP WITHOUT TIME ZONE NOT NULL,

  -- Wiki article that contains this comment, to allow editing, revision
  -- history and more. This should be joined with `page` and `text` to get
  -- the actual comment text.
  wlc_comment_page INTEGER,

  PRIMARY KEY (wlc_id)

) /*$wgDBTableOptions*/;

CREATE INDEX wlc_post_thread ON /*$wgDBprefix*/wikilog_comments (wlc_post, wlc_thread);
CREATE INDEX wlc_timestamp ON /*$wgDBprefix*/wikilog_comments (wlc_timestamp);
CREATE INDEX wlc_updated ON /*$wgDBprefix*/wikilog_comments (wlc_updated);
CREATE INDEX wlc_comment_page ON /*$wgDBprefix*/wikilog_comments (wlc_comment_page);
