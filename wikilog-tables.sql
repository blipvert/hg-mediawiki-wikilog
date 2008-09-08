-- Tables used by the MediaWiki Wikilog extension.
-- Juliano F. Ravasi, 2008

CREATE TABLE /*$wgDBprefix*/wikilog_posts (
	wlp_page	INTEGER UNSIGNED REFERENCES page(page_id) ON DELETE CASCADE,
	wlp_publish	BOOL NOT NULL DEFAULT '0',
	wlp_pubdate	BINARY(14) NOT NULL,
	wlp_authors	BLOB NOT NULL,
	wlp_tags	BLOB NOT NULL,

	PRIMARY KEY (wlp_page),
	INDEX wlp_pubdate (wlp_pubdate)
) /*$wgDBTableOptions*/;

CREATE TABLE /*$wgDBprefix*/wikilog_authors (
	wla_page	INTEGER UNSIGNED REFERENCES page(page_id) ON DELETE CASCADE,
	wla_author	INTEGER UNSIGNED REFERENCES user(user_id) ON DELETE CASCADE,
	wla_author_text	VARCHAR(255) BINARY NOT NULL,

	PRIMARY KEY (wla_page, wla_author_text),
	INDEX wla_author (wla_author)
	INDEX wla_author_text (wla_author_text)
) /*$wgDBTableOptions*/;

CREATE TABLE /*$wgDBprefix*/wikilog_tags (
	wlt_page	INTEGER UNSIGNED REFERENCES page(page_id) ON DELETE CASCADE,
	wlt_tag		VARCHAR(255) BINARY NOT NULL,

	PRIMARY KEY (wlt_page, wlt_tag),
	INDEX wlt_tag (wlt_tag)
) /*$wgDBTableOptions*/;
