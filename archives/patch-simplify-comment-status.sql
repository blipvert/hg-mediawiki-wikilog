-- Tables used by the MediaWiki Wikilog extension.
-- Juliano F. Ravasi, 2009
--
-- You should not have to create these tables manually unless you are doing
-- a manual installation. In normal conditions, maintenance/update.php should
-- perform any needed database setup.
--

-- This patch should only be applied if you used the trunk version of
-- Wikilog between r383 and r410.

ALTER TABLE /*$wgDBprefix*/wikilog_comments
  CHANGE wlc_status
  wlc_status ENUM( 'OK', 'PENDING', 'DELETED' ) NOT NULL DEFAULT 'OK';
