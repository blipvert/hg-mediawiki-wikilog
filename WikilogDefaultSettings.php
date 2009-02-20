<?php
/**
 * MediaWiki Wikilog extension
 * Copyright © 2008, 2009 Juliano F. Ravasi
 * http://www.mediawiki.org/wiki/Extension:Wikilog
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

/**
 * @addtogroup Extensions
 * @author Juliano F. Ravasi < dev juliano info >
 */

if ( !defined( 'MEDIAWIKI' ) )
	die();

/*
 *              --- DO NOT MAKE CHANGES TO THESE VALUES ---
 *
 * In order to configure the extension, copy the variables you want to change
 * to your LocalSettings.php file, and change them there, not here.
 */

/**
 * A string in the format "example.org,date", according to RFC 4151, that will
 * be used as taggingEntity in order to create feed item tags.
 */
$wgTaggingEntity = false;

/**
 * Path of Wikilog style and image files.
 * Defaults to "$wgScriptPath/extensions/Wikilog/style".
 */
$wgWikilogStylePath = false;

/**
 * Maximum number of items in wikilog front page.
 */
$wgWikilogSummaryLimit = $wgFeedLimit;

/**
 * Default number of articles to list on the wikilog front page.
 */
$wgWikilogNumArticles = 20;

/**
 * Maximum number of authors of a wikilog post.
 */
$wgWikilogMaxAuthors = 6;

/**
 * Enable use of tags. This is disabled by default since MediaWiki category
 * system already provides similar functionality, and are the preferred way
 * of organizing wikilog posts. Enable this if you want or need an additional
 * mechanism for organizing that is independent from categories, and specific
 * for wikilog posts.
 *
 * Even if disabled, tags are still recorded. This configuration only affects
 * the ability of performing queries based on tags. This is so that it could
 * be enabled and disabled without having to perform maintenance on the
 * database.
 */
$wgWikilogEnableTags = false;

/**
 * Maximum number of tags in a wikilog post.
 */
$wgWikilogMaxTags = 25;

/**
 * Syndication feed classes. Similar to $wgFeedClasses.
 */
$wgWikilogFeedClasses = array(
	'atom' => 'WlAtomFeed',
	'rss'  => 'WlRSSFeed'
);

/**
 * Enable or disable output of summary or content in wikilog feeds. At least
 * one of them MUST be true.
 */
$wgWikilogFeedSummary = true;
$wgWikilogFeedContent = true;

/**
 * Enable output of article categories in wikilog feeds.
 */
$wgWikilogFeedCategories = true;

/**
 * Enable output of external references in wikilog feeds.
 */
$wgWikilogFeedRelated = false;

/**
 * Navigation bars to show in listing pages.
 */
$wgWikilogNavTop = false;
$wgWikilogNavBottom = true;

/**
 * Namespaces used for wikilogs.
 */
$wgWikilogNamespaces = array();

