<?php
/**
 * MediaWiki Wikilog extension
 * Copyright © 2008 Juliano F. Ravasi < dev at juliano info >
 * http://juliano.info/en/Projects/MediaWiki_Wikilog
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
 *
 * Adds blogging features to MediaWiki, through a special namespace,
 * making it a wiki-blog hybrid, like a Bliki.
 */

if ( !defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name'				=> "Wikilog",
	'version'			=> "0.5.0",
	'author'			=> "Juliano F. Ravasi",
	'description'		=> "Adds blogging features, creating a wiki-blog hybrid.",
	'descriptionmsg'	=> "wikilog-desc",
// 	'url'				=> "-",
);

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
	'WikilogMainPage'		=> $dir . 'WikilogMainPage.php',
	'WikilogItemPage'		=> $dir . 'WikilogItemPage.php',
	'WikilogCommentsPage'	=> $dir . 'WikilogCommentsPage.php',
	'WikilogLinksUpdate'	=> $dir . 'WikilogLinksUpdate.php',
	'WikilogItemQuery'		=> $dir . 'WikilogQuery.php',
	'WikilogSummaryPager'	=> $dir . 'WikilogPager.php',
	'WikilogArchivesPager'	=> $dir . 'WikilogPager.php',
	'WikilogFeed'			=> $dir . 'WikilogFeed.php',
// 	'SpecialWikilog'		=> $dir . 'SpecialWikilog.php',
	'WlSyndicationBase'		=> $dir . 'WlFeed.php',
	'WlSyndicationFeed'		=> $dir . 'WlFeed.php',
	'WlSyndicationEntry'	=> $dir . 'WlFeed.php',
	'WlTextConstruct'		=> $dir . 'WlFeed.php',
	'WlAtomFeed'			=> $dir . 'WlFeed.php',
	'WlRSSFeed'				=> $dir . 'WlFeed.php',
);

/*
 * Special pages.
 */
// $wgSpecialPages['Wikilog'] = 'SpecialWikilog';

/*
 * Hooks.
 */
$wgExtensionFunctions[] = "Wikilog::Setup";

// Main Wikilog hooks
$wgHooks['ArticleFromTitle'][] = 'Wikilog::ArticleFromTitle';
$wgHooks['ArticleEditUpdatesDeleteFromRecentchanges'][] = 'Wikilog::ArticleEditUpdates';
$wgHooks['ArticleDeleteComplete'][] = 'Wikilog::ArticleDeleteComplete';
$wgHooks['TitleMoveComplete'][] = 'Wikilog::TitleMoveComplete';
$wgHooks['LanguageGetSpecialPageAliases'][] = 'Wikilog::LanguageGetSpecialPageAliases';
$wgHooks['LanguageGetMagic'][] = 'Wikilog::LanguageGetMagic';

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
 * Namespaces used for wikilogs.
 */
$wgWikilogNamespaces = array();


/**
 * Main Wikilog class. Used as a namespace. No instances of this class are
 * intended to exist, all member functions are static.
 */
class Wikilog {

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
		/* TODO */
		$magicWords['wl-publish'] = array( 0, 'wl-publish' );
		$magicWords['wl-tags'   ] = array( 0, 'wl-tags'    );
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
			$list = array_keys( $list );
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

