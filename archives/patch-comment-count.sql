-- Tables used by the MediaWiki Wikilog extension.
-- Juliano F. Ravasi, 2008
--
-- You should not have to create these tables manually unless you are doing
-- a manual installation. In normal conditions, maintenance/update.php should
-- perform any needed database setup.
--

ALTER TABLE /*$wgDBprefix*/wikilog_posts
  MODIFY COLUMN wlp_updated BINARY(14) NOT NULL AFTER wlp_pubdate,
  ADD COLUMN wlp_num_comments INTEGER UNSIGNED;
