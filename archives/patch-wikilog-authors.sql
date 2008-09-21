-- Tables used by the MediaWiki Wikilog extension.
-- Juliano F. Ravasi, 2008
--
-- You should not have to create these tables manually unless you are doing
-- a manual installation. In normal conditions, maintenance/update.php should
-- perform any needed database setup.
--

ALTER TABLE /*$wgDBprefix*/wikilog_wikilogs
  ADD COLUMN wlw_authors BLOB NOT NULL AFTER wlw_logo;
