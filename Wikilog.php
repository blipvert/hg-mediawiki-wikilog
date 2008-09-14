<?php
/**
 * MediaWiki Wikilog extension
 * Copyright © 2008 Juliano F. Ravasi
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
 * General extension information.
 */
$wgExtensionCredits['specialpage'][] = array(
	'name'				=> 'Wikilog',
	'version'			=> '0.5.0',
	'author'			=> 'Juliano F. Ravasi',
	'description'		=> 'Adds blogging features, creating a wiki-blog hybrid.',
	'descriptionmsg'	=> 'wikilog-desc',
	'url'				=> 'http://www.mediawiki.org/wiki/Extension:Wikilog',
);


/*
 * Dependencies.
 */
require_once( dirname(__FILE__) . '/WlFeed.php' );


$dir = dirname(__FILE__) . '/';

/*
 * Messages.
 */
$wgExtensionMessagesFiles['Wikilog'] = $dir . 'Wikilog.i18n.php';

/*
 * Autoloaded classes.
 */
$wgAutoloadClasses += array(
	'WikilogParser'			=> $dir . 'WikilogParser.php',
	'WikilogParserCache'	=> $dir . 'WikilogParser.php',
	'WikilogMainPage'		=> $dir . 'WikilogMainPage.php',
	'WikilogItemPage'		=> $dir . 'WikilogItemPage.php',
	'WikilogCommentsPage'	=> $dir . 'WikilogCommentsPage.php',
	'WikilogLinksUpdate'	=> $dir . 'WikilogLinksUpdate.php',
	'WikilogItemQuery'		=> $dir . 'WikilogQuery.php',
	'WikilogSummaryPager'	=> $dir . 'WikilogPager.php',
	'WikilogArchivesPager'	=> $dir . 'WikilogPager.php',
	'WikilogFeed'			=> $dir . 'WikilogFeed.php',
	'SpecialWikilog'		=> $dir . 'SpecialWikilog.php',
);

/*
 * Special pages.
 */
$wgSpecialPages['Wikilog'] = 'SpecialWikilog';
$wgSpecialPageGroups['Wikilog'] = 'changes';

/*
 * Hooks.
 */
$wgExtensionFunctions[] = "Wikilog::Setup";

// Main Wikilog hooks
$wgHooks['ArticleFromTitle'][] = 'Wikilog::ArticleFromTitle';
$wgHooks['ArticleEditUpdatesDeleteFromRecentchanges'][] = 'Wikilog::ArticleEditUpdates';
$wgHooks['ArticleDeleteComplete'][] = 'Wikilog::ArticleDeleteComplete';
$wgHooks['TitleMoveComplete'][] = 'Wikilog::TitleMoveComplete';
$wgHooks['GetLocalURL'][] = 'Wikilog::GetLocalURL';
$wgHooks['GetFullURL'][] = 'Wikilog::GetFullURL';
$wgHooks['PageRenderingHash'][] = 'Wikilog::PageRenderingHash';
$wgHooks['LanguageGetSpecialPageAliases'][] = 'Wikilog::LanguageGetSpecialPageAliases';
$wgHooks['LanguageGetMagic'][] = 'Wikilog::LanguageGetMagic';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'Wikilog::ExtensionSchemaUpdates';

// WikilogLinksUpdate hooks
$wgHooks['LinksUpdate'][] = 'WikilogLinksUpdate::LinksUpdate';

// WikilogParser hooks
$wgHooks['ParserFirstCallInit'][] = 'WikilogParser::registerParser';
$wgHooks['ParserClearState'][] = 'WikilogParser::clearState';
$wgHooks['ParserAfterTidy'][] = 'WikilogParser::afterTidy';


/*
 * Configuration.
 */

/**
 * A string in the format "example.org,date", according to RFC 4151, that will
 * be used as taggingEntity in order to create feed item tags.
 */
$wgTaggingEntity = false;

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
 * Namespaces used for wikilogs.
 */
$wgWikilogNamespaces = array();


/**
 * Main Wikilog class. Used as a namespace. No instances of this class are
 * intended to exist, all member functions are static.
 */
class Wikilog {

	/**
	 * True if parsing articles with feed output specific settings.
	 * This is an horrible hack needed because of many MediaWiki misdesigns.
	 */
	static public $feedParsing = false;

	/**
	 * True if we are expanding local URLs (in order to render stand-alone,
	 * base-less feeds). This is an horrible hack needed because of many
	 * MediaWiki misdesigns.
	 */
	static public $expandingUrls = false;

	/**
	 * Original paths before expansion.
	 */
	static public $originalPaths = null;

	###
	##  MediaWiki hooks.
	#

	/**
	 * Extension setup function.
	 */
	static function Setup() {
		global $wgWikilogNamespaces, $wgNamespacesWithSubpages;

		# Find assigned namespaces and make sure they have subpages
		foreach ( $wgWikilogNamespaces as $ns ) {
			$wgNamespacesWithSubpages[$ns  ] = true;
			$wgNamespacesWithSubpages[$ns^1] = true;
		}

		# Work around bug in MediaWiki 1.13 when '?action=render'.
		# https://bugzilla.wikimedia.org/show_bug.cgi?id=15512
		global $wgRequest;
		if ( $wgRequest->getVal( 'action' ) == 'render' ) {
			self::expandLocalUrls();
		}
	}

	/**
	 * ArticleFromTitle hook handler function.
	 * Detects if the article is a wikilog article (self::getWikilogInfo
	 * returns an instance of WikilogInfo) and returns the proper class
	 * instance for the article.
	 */
	static function ArticleFromTitle( &$title, &$article ) {
		if ( ( $wi = self::getWikilogInfo( $title ) ) ) {
			if ( $title->isTalkPage() ) {
				$article = new WikilogCommentsPage( $title, $wi );
			} else if ( $wi->isItem() ) {
				$article = new WikilogItemPage( $title, $wi );
			} else {
				$article = new WikilogMainPage( $title, $wi );
			}
			return false;	// stop processing
		}
		return true;
	}

	/**
	 * ArticleEditUpdatesDeleteFromRecentchanges hook handler function.
	 * Performs post-edit updates if article is a wikilog article.
	 */
	static function ArticleEditUpdates( &$article ) {
		$wi = self::getWikilogInfo( $article->getTitle() );

		if ( $wi && $wi->isItem() ) {
			if ( $article->getTitle()->isTalkPage() ) {
				# ::WikilogCommentsPage::
				# Invalidate cache of wikilog item page.
				if ( $wi->getItemTitle()->exists() ) {
					$wi->getItemTitle()->invalidateCache();
					$wi->getItemTitle()->purgeSquid();
				}
			} else {
				# ::WikilogItemPage::
				$dbw = wfGetDB( DB_MASTER );
				$editInfo = $article->mPreparedEdit;

				# Check if we have any wikilog metadata available.
				if ( isset( $editInfo->output->mExtWikilog ) ) {
					$output = $editInfo->output->mExtWikilog;

					# If no date was provided, use date of current revision.
					if ( !$output->mPubDate ) {
						$revision = Revision::newFromId( $article->getLatest() );
						$output->mPubDate = $revision->getTimestamp();
					}

					# Update entry in wikilog_posts table.
					$dbw->replace(
						'wikilog_posts',
						'wlp_page',
						array(
							'wlp_page' => $article->getId(),
							'wlp_publish' => $output->mPublish,
							'wlp_pubdate' => $output->mPubDate,
							'wlp_authors' => serialize( $output->mAuthors ),
							'wlp_tags' => serialize( $output->mTags )
						),
						__METHOD__
					);
				} else {
					# Remove entry in wikilog_posts table.
					$dbw->delete(
						'wikilog_posts',
						array( 'wlp_page' => $article->getId() ),
						__METHOD__
					);
				}

				# Invalidate cache of parent wikilog page.
				if ( $wi->getTitle()->exists() ) {
					$wi->getTitle()->invalidateCache();
					$wi->getTitle()->purgeSquid();
				}
			}
		}

		return true;
	}

	/**
	 * ArticleDeleteComplete hook handler function.
	 * Purges wikilog metadata when an article is deleted.
	 * @note This function REQUIRES MediaWiki 1.13 or higher ($id parameter).
	 */
	static function ArticleDeleteComplete( &$article, &$user, $reason, $id )
	{
		# Retrieve wikilog information.
		$wi = self::getWikilogInfo( $article->getTitle() );

		# Take special procedures if it is a wikilog item page.
		if ( $wi && $wi->isItem() ) {
			$dbw = wfGetDB( DB_MASTER );

			# Delete table entries.
			$dbw->delete( 'wikilog_posts', array( 'wlp_page' => $id ) );
			$dbw->delete( 'wikilog_authors', array( 'wla_page' => $id ) );
			$dbw->delete( 'wikilog_tags', array( 'wlt_page' => $id ) );

			# Invalidate cache of parent wikilog page.
			$wl = $wi->getTitle();
			if ( $wl->exists() ) {
				$wl->invalidateCache();
				$wl->purgeSquid();
			}
		}

		return true;
	}

	/**
	 * TitleMoveComplete hook handler function.
	 * Handles moving articles to and from wikilog namespaces.
	 */
	static function TitleMoveComplete( &$oldtitle, &$newtitle, &$user, $pageid,
			$redirid )
	{
		global $wgWikilogNamespaces;

		$oldwl = in_array( ( $oldns = $oldtitle->getNamespace() ), $wgWikilogNamespaces );
		$newwl = in_array( ( $newns = $newtitle->getNamespace() ), $wgWikilogNamespaces );

		if ( $oldwl && $newwl ) {
			# Moving titles in wikilog namespaces.
			## Nothing to do.
			wfDebug( __METHOD__ . ": Moving title in wikilog namespaces ".
				"($oldns, $newns)." );
		} else if ( $oldwl ) {
			# Moving from wikilog namespace to normal namespace.
			# Purge wikilog data.
			wfDebug( __METHOD__ . ": Moving from wikilog namespace to other ".
				"namespace ($oldns, $newns). Purging wikilog data." );
			$dbw = wfGetDB( DB_MASTER );
			$dbw->delete( 'wikilog_posts', array( 'wlp_page' => $pageid ) );
			$dbw->delete( 'wikilog_authors', array( 'wla_page' => $pageid ) );
			$dbw->delete( 'wikilog_tags', array( 'wlt_page' => $pageid ) );
		} else if ( $newwl ) {
			# Moving from normal namespace to wikilog namespace.
			# Create wikilog data.
			wfDebug( __METHOD__ . ": Moving from other namespace to wikilog ".
				"namespace ($oldns, $newns). Creating wikilog data." );
		}
		return true;
	}

	/**
	 * GetLocalURL hook handler function.
	 * Expands local URL @a $url if self::$expandingUrls is true.
	 */
	static function GetLocalURL( &$title, &$url, $query ) {
		if ( self::$expandingUrls ) {
			$url = wfExpandUrl( $url );
		}
		return true;
	}

	/**
	 * GetFullURL hook handler function.
	 * Fix some brain-damage in Title::getFullURL() (as of MW 1.13) that
	 * prepends $wgServer to URL without using wfExpandUrl(), in part because
	 * we want (above in Wikilog::GetLocalURL()) to return an absolute URL
	 * from Title::getLocalURL() in situations where action != 'render'.
	 * @todo Report this bug to MediaWiki bugzilla.
	 */
	static function GetFullURL( &$title, &$url, $query ) {
		global $wgServer;
		if ( self::$expandingUrls ) {
			$l = strlen( $wgServer );
			if ( substr( $url, 0, 2*$l ) == $wgServer.$wgServer ) {
				$url = substr( $url, $l );
			}
		}
		return true;
	}

	/**
	 * PageRenderingHash hook handler function.
	 * Add a tag to page rendering hash if self::$expandingUrls is true.
	 */
	static function PageRenderingHash( &$confstr ) {
		# This doesn't work as expected. This value is cached upon the first
		# call. So if the first cache hit has different settings than the
		# following ones, those will hit the wrong entries in the cache.
// 		if ( self::$expandingUrls ) {
// 			$confstr .= '!wl-expurls';
// 		}
		return true;
	}

	/**
	 * LanguageGetSpecialPageAliases hook handler function.
	 * Adds language aliases for special pages.
	 */
	static function LanguageGetSpecialPageAliases( &$specialPageAliases, $lang ) {
		wfLoadExtensionMessages( 'Wikilog' );
		$title = Title::newFromText( wfMsg( 'wikilog-specialwikilog' ) );
		$specialPageAliases['SpecialWikilog'][] = $title->getDBKey();
		return true;
	}

	/**
	 * LanguageGetMagic hook handler function.
	 * Adds language aliases for magic words.
	 */
	static function LanguageGetMagic( &$magicWords, $lang ) {
		/// TODO: Language magic.
		$magicWords['wl-publish'] = array( 0, 'wl-publish' );
		$magicWords['wl-tags'   ] = array( 0, 'wl-tags'    );
		return true;
	}


	static function ExtensionSchemaUpdates() {
		global $wgDBtype, $wgExtNewFields, $wgExtPGNewFields, $wgExtNewIndexes, $wgExtNewTables;

		$dir = dirname(__FILE__) . '/';
		if( $wgDBtype == 'mysql' ) {
			$wgExtNewTables += array(
				array( 'wikilog_posts',   $dir . 'wikilog-tables.sql' ),
				array( 'wikilog_authors', $dir . 'wikilog-tables.sql' ),
				array( 'wikilog_tags',    $dir . 'wikilog-tables.sql' )
			);
		} else if( $wgDBtype == 'postgres' ) {
			/// TODO: PostgreSQL tables.
			print "\n".
				"Warning: There are no PostgreSQL table structures for the\n".
				"Wikilog extension at this moment.\n\n";
		}
		return true;
	}



	###
	##  Other global wikilog functions.
	#

	/**
	 * Returns wikilog information for the given title.
	 * This function checks if @a $title is an article title in a wikilog
	 * namespace, and returns an appropriate WikilogInfo instance if so.
	 *
	 * @param $title Article title object.
	 * @returns WikilogInfo instance, or NULL.
	 */
	static function getWikilogInfo( $title ) {
		global $wgWikilogNamespaces;

		$ns = Namespace::getSubject( $title->getNamespace() );
		if ( in_array( $ns, $wgWikilogNamespaces ) ) {
			return new WikilogInfo( $title );
		} else {
			return null;
		}
	}

	/**
	 * Enable special wikilog feed parsing.
	 *
	 * This function changes the parser behavior in order to output
	 *
	 * The proper way to use this function is:
	 * @code
	 *   $saveFeedParse = Wikilog::enableFeedParsing();
	 *   # ...code that uses $wgParser in order to parse articles...
	 *   Wikilog::enableFeedParsing( $saveFeedParse );
	 * @endcode
	 *
	 * @note Using this function changes the behavior of Parser. When enabled,
	 *   parsed content should be cached under a different key than when
	 *   disabled.
	 */
	static function enableFeedParsing( $enable = true ) {
		$prev = self::$feedParsing;
		self::$feedParsing = $enable;
		return $prev;
	}

	/**
	 * Enable expansion of local URLs.
	 *
	 * In order to output stand-alone content with all absolute links, it is
	 * necessary to expand local URLs. MediaWiki tries to do this in a few
	 * places by sniffing into the 'action' GET request parameter, but this
	 * fails in many ways. This function tries to remedy this.
	 *
	 * This function pre-expands all base URL fragments used by MediaWiki,
	 * and also enables URL expansion in the Wikilog::GetLocalURL hook.
	 * The original values of all URLs are saved when $enable = true, and
	 * restored back when $enabled = false.
	 *
	 * The proper way to use this function is:
	 * @code
	 *   $saveExpUrls = Wikilog::expandLocalUrls();
	 *   # ...code that uses $wgParser in order to parse articles...
	 *   Wikilog::expandLocalUrls( $saveExpUrls );
	 * @endcode
	 *
	 * @note Using this function changes the behavior of Parser. When enabled,
	 *   parsed content should be cached under a different key than when
	 *   disabled.
	 */
	static function expandLocalUrls( $enable = true ) {
		global $wgScriptPath, $wgUploadPath, $wgStylePath, $wgMathPath, $wgLocalFileRepo;
		$prev = self::$expandingUrls;

		if ( $enable ) {
			if ( !self::$expandingUrls ) {
				self::$expandingUrls = true;

				# Save original values.
				self::$originalPaths = array( $wgScriptPath, $wgUploadPath,
					$wgStylePath, $wgMathPath, $wgLocalFileRepo['url'] );

				# Expand paths.
				$wgScriptPath = wfExpandUrl( $wgScriptPath );
				$wgUploadPath = wfExpandUrl( $wgUploadPath );
				$wgStylePath  = wfExpandUrl( $wgStylePath  );
				$wgMathPath   = wfExpandUrl( $wgMathPath   );
				$wgLocalFileRepo['url'] = wfExpandUrl( $wgLocalFileRepo['url'] );
			}
		} else {
			if ( self::$expandingUrls ) {
				self::$expandingUrls = false;

				# Restore original values.
				list( $wgScriptPath, $wgUploadPath, $wgStylePath, $wgMathPath,
					$wgLocalFileRepo['url'] ) = self::$originalPaths;
			}
		}

		return $prev;
	}

	/**
	 * Split summary of a wikilog post from the contents.
	 * If summary was provided in <summary>...</summary> tags, use it,
	 * otherwise, use some heuristics to find it in the content.
	 */
	static function splitSummaryContent( $parserOutput ) {
		$content = Sanitizer::removeHTMLcomments( $parserOutput->getText() );

		if ( isset( $parserOutput->mExtWikilog ) && $parserOutput->mExtWikilog->mSummary ) {
			$summary = Sanitizer::removeHTMLcomments( $parserOutput->mExtWikilog->mSummary );
		} else {
			$blocks = preg_split( '/< (h[1-6]) .*? > .*? <\\/\\1>/ix', $content );

			if ( count( $blocks ) > 1 ) {
				# Long article, get only the first paragraph.
				$pextr = '/<(p)
					( \\s+ (?: [^\'"\\/>] | \'[^\']*\' | "[^"]*" )* )?
					(?: > .*? <\\/\\1\\s*> | \\/> )/isx';

				if ( preg_match_all( $pextr, $blocks[0], $m ) ) {
					$summary = implode( "\n", $m[0] );
				} else {
					$summary = null;
				}
			} else {
				# Short article, no summary.
				$summary = null;
			}
		}

		return array( $summary, $content );
	}

	/**
	 * Formats a list of authors.
	 * Given a list of authors, this function formats it in wiki syntax,
	 * with links to their user and user-talk pages, according to the
	 * 'wikilog-author-signature' system message.
	 *
	 * @param $list Array of authors.
	 * @return Wikitext-formatted textual list of authors.
	 */
	static function authorList( $list ) {
		wfLoadExtensionMessages( 'Wikilog' );

		if ( is_string( $list ) ) {
			return self::authorSig( $list );
		}
		else if ( is_array( $list ) ) {
			$count = count( $list );

			if ( $count == 0 ) {
				return '';
			}
			else if ( $count == 1 ) {
				return self::authorSig( $list[0] );
			}
			else {
				$first = implode( ', ', array_map( 'Wikilog::authorSig',
					array_slice( $list, 0, $count - 1 ) ) );
				$last = self::authorSig( $list[$count-1] );
				$and = wfMsgForContent( 'and' );
				return "{$first} {$and} {$last}";
			}
		}
		else {
			return '';
		}
	}

	/**
	 * Formats a single author signature.
	 * Uses the 'wikilog-author-signature' system message, in order to provide
	 * user and user-talk links.
	 *
	 * @param $author String, author name.
	 * @return Wikitext-formatted author signature.
	 */
	static function authorSig( $author ) {
		static $authorSigCache = array();
		if ( !isset( $authorSigCache[$author] ) )
			$authorSigCache[$author] = wfMsgForContent( 'wikilog-author-signature', $author );
		return $authorSigCache[$author];
	}

	/**
	 * Retrieves an article parsed ouput either from parser cache or by
	 * parsing it again. If parsing again, stores it back into parser cache.
	 *
	 * @note This should really be part of MediaWiki, but it doesn't provide
	 *   such convenient functionality in a single function, and we have to
	 *   implement it here.
	 *
	 * @param $title Article title object.
	 * @return Two-element array containing the article and its parser output.
	 */
	static function parsedArticle( Title $title, $feed = false ) {
		global $wgUser, $wgParser, $wgEnableParserCache;

		if ( $feed ) {
			// Enable some feed-specific behavior.
			$saveFeedParse = Wikilog::enableFeedParsing();
			$saveExpUrls = Wikilog::expandLocalUrls();

			// Select parser cache.
			$parserCache = WikilogParserCache::singleton();

			// Parser options.
			$parserOpt = ParserOptions::newFromUser( $wgUser );
			$parserOpt->setTidy( true );
			$parserOpt->setEditSection( false );
		} else {
			// Select parser cache.
			$parserCache = ParserCache::singleton();

			// Parser options.
			$parserOpt = ParserOptions::newFromUser( $wgUser );
			$parserOpt->setTidy( true );
			$parserOpt->enableLimitReport();
		}

		$article = new Article( $title );

		if ( $wgEnableParserCache ) {
			$parserOutput = $parserCache->get( $article, $wgUser );
			if ( !$parserOutput ) {
				$arttext = $article->fetchContent();
				$parserOutput = $wgParser->parse( $arttext, $title, $parserOpt );
				if ( $parserOutput->getCacheTime() != -1 ) {
					$parserCache->save( $parserOutput, $article, $wgUser );
				}
			}
		} else {
			$arttext = $article->fetchContent();
			$parserOutput = $wgParser->parse( $arttext, $title, $parserOpt );
		}

		if ( $feed ) {
			// Restore default behavior.
			Wikilog::enableFeedParsing( $saveFeedParse );
			Wikilog::expandLocalUrls( $saveExpUrls );
		}

		return array( $article, $parserOutput );
	}
}


/**
 * Wikilog information class.
 * This class represents relationship information about a wikilog article,
 * given its title. It is used to derive the main wikilog article name or the
 * comments page name from the wikilog post, for example.
 */
class WikilogInfo {

	public $mWikilogName;		///< Wikilog title (textual string).
	public $mWikilogTitle;		///< Wikilog main article title object.
	public $mItemName;			///< Wikilog post title (textual string).
	public $mItemTitle;			///< Wikilog post title object.

	/**
	 * Constructor.
	 * @param $title Title object.
	 */
	function __construct( $title ) {
		$ns = Namespace::getSubject( $title->getNamespace() );
		if ( strpos( $title->getText(), '/' ) !== false ) {
			list( $this->mWikilogName, $this->mItemName ) =
				explode( '/', $title->getText(), 2 );
			$this->mWikilogTitle = Title::makeTitle( $ns, $this->mWikilogName );
			$this->mItemTitle = Title::makeTitle( $ns, $title->getText() );
		} else {
			$this->mWikilogName = $title->getText();
			$this->mWikilogTitle = Title::makeTitle( $ns, $this->mWikilogName );
			$this->mItemName = null;
			$this->mItemTitle = null;
		}
	}

	function isItem() { return $this->mItemTitle !== null; }
	function getName() { return $this->mWikilogName; }
	function getTitle() { return $this->mWikilogTitle; }
	function getItemName() { return $this->mItemName; }
	function getItemTitle() { return $this->mItemTitle; }

}

