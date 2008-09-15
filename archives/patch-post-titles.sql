-- Tables used by the MediaWiki Wikilog extension.
-- Juliano F. Ravasi, 2008
--
-- You should not have to create these tables manually unless you are doing
-- a manual installation. In normal conditions, maintenance/update.php should
-- perform any needed database setup.
--

ALTER TABLE /*$wgDBprefix*/wikilog_posts
  ADD COLUMN wlp_parent INTEGER UNSIGNED NOT NULL AFTER wlp_page,
  ADD COLUMN wlp_title VARCHAR(255) BINARY NOT NULL AFTER wlp_parent,
  ADD COLUMN wlp_updated BINARY(14) NOT NULL AFTER wlp_tags,
  ADD INDEX wlp_parent (wlp_parent),
  ADD INDEX wlp_title (wlp_title),
  ADD INDEX wlp_updated (wlp_updated);

UPDATE /*$wgDBprefix*/wikilog_posts,
       /*$wgDBprefix*/page AS p,
       /*$wgDBprefix*/page AS w
  SET wlp_parent = w.page_id,
      wlp_title = SUBSTRING(p.page_title FROM POSITION('/' IN p.page_title)+1)
  WHERE wlp_page = p.page_id AND
        p.page_namespace = w.page_namespace AND
        SUBSTRING(p.page_title FROM 1 FOR POSITION('/' IN p.page_title)-1) = w.page_title;

UPDATE /*$wgDBprefix*/wikilog_posts,
       /*$wgDBprefix*/page,
       /*$wgDBprefix*/revision
  SET wlp_updated = rev_timestamp
  WHERE wlp_page = page_id AND
        page_latest = rev_id;

REPLACE INTO /*$wgDBprefix*/wikilog_wikilogs (wlw_page, wlw_updated)
  SELECT wlp_parent, greatest(max(pr.rev_timestamp), wr.rev_timestamp)
    FROM /*$wgDBprefix*/wikilog_posts
      LEFT JOIN /*$wgDBprefix*/page     AS w  ON (wlp_parent = w.page_id)
      LEFT JOIN /*$wgDBprefix*/revision AS wr ON (w.page_latest = wr.rev_id)
      LEFT JOIN /*$wgDBprefix*/page     AS p  ON (wlp_page = p.page_id)
      LEFT JOIN /*$wgDBprefix*/revision AS pr ON (p.page_latest = pr.rev_id)
      GROUP BY wlp_parent;
