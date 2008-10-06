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
	'version'			=> '0.6.3svn',
	'author'			=> 'Juliano F. Ravasi',
	'description'		=> 'Adds blogging features, creating a wiki-blog hybrid.',
	'descriptionmsg'	=> 'wikilog-desc',
	'url'				=> 'http://www.mediawiki.org/wiki/Extension:Wikilog',
);


/*
 * Dependencies.
 */
require_once( dirname(__FILE__) . '/WlFeed.php' );


/*
 * Messages.
 */
$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['Wikilog'] = $dir . 'Wikilog.i18n.php';

/*
 * Autoloaded classes.
 */
$wgAutoloadClasses += array(
	// General
	'WikilogFeed'			=> $dir . 'WikilogFeed.php',
	'WikilogHooks'			=> $dir . 'WikilogHooks.php',
	'WikilogItemQuery'		=> $dir . 'WikilogQuery.php',
	'WikilogLinksUpdate'	=> $dir . 'WikilogLinksUpdate.php',
	'WikilogUtils'			=> $dir . 'WikilogUtils.php',
	'SpecialWikilog'		=> $dir . 'SpecialWikilog.php',

	// WikilogParser.php
	'WikilogParser'			=> $dir . 'WikilogParser.php',
	'WikilogParserOutput'	=> $dir . 'WikilogParser.php',
	'WikilogParserCache'	=> $dir . 'WikilogParser.php',

	// WikilogPager.php
	'WikilogSummaryPager'	=> $dir . 'WikilogPager.php',
	'WikilogTemplatePager'	=> $dir . 'WikilogPager.php',
	'WikilogArchivesPager'	=> $dir . 'WikilogPager.php',

	// Namespace pages
	'WikilogMainPage'		=> $dir . 'WikilogMainPage.php',
	'WikilogItemPage'		=> $dir . 'WikilogItemPage.php',
	'WikilogCommentsPage'	=> $dir . 'WikilogCommentsPage.php',
);

/*
 * Special pages.
 */
$wgSpecialPages['Wikilog'] = 'SpecialWikilog';
$wgSpecialPageGroups['Wikilog'] = 'changes';

/*
 * Hooks.
 */
$wgExtensionFunctions[] = array( 'Wikilog', 'ExtensionInit' );

// Main Wikilog hooks
$wgHooks['ArticleFromTitle'][]			= 'Wikilog::ArticleFromTitle';
$wgHooks['SkinTemplateTabs'][]			= 'Wikilog::SkinTemplateTabs';
$wgHooks['BeforePageDisplay'][]			= 'Wikilog::BeforePageDisplay';

// General Wikilog hooks
$wgHooks['ArticleEditUpdatesDeleteFromRecentchanges'][]
										= 'WikilogHooks::ArticleEditUpdates';
$wgHooks['ArticleDeleteComplete'][]		= 'WikilogHooks::ArticleDeleteComplete';
$wgHooks['TitleMoveComplete'][]			= 'WikilogHooks::TitleMoveComplete';
$wgHooks['LanguageGetSpecialPageAliases'][]
										= 'WikilogHooks::LanguageGetSpecialPageAliases';
$wgHooks['LanguageGetMagic'][]			= 'WikilogHooks::LanguageGetMagic';
$wgHooks['LoadExtensionSchemaUpdates'][]= 'WikilogHooks::ExtensionSchemaUpdates';
$wgHooks['UnknownAction'][]				= 'WikilogHooks::UnknownAction';

// WikilogLinksUpdate hooks
$wgHooks['LinksUpdate'][]				= 'WikilogLinksUpdate::LinksUpdate';

// WikilogParser hooks
$wgHooks['ParserFirstCallInit'][]		= 'WikilogParser::FirstCallInit';
$wgHooks['ParserClearState'][]			= 'WikilogParser::ClearState';
$wgHooks['ParserBeforeInternalParse'][]	= 'WikilogParser::BeforeInternalParse';
$wgHooks['ParserAfterTidy'][]			= 'WikilogParser::AfterTidy';
$wgHooks['GetLocalURL'][]				= 'WikilogParser::GetLocalURL';
$wgHooks['GetFullURL'][]				= 'WikilogParser::GetFullURL';


/*
 * Default settings.
 */
require_once( dirname(__FILE__) . '/WikilogDefaultSettings.php' );


/**
 * Main Wikilog class. Used as a namespace. No instances of this class are
 * intended to exist, all member functions are static.
 */
class Wikilog {

	###
	##  Setup functions.
	#

	/**
	 * Create a namespace, associating wikilog features to it.
	 *
	 * @param $ns Subject namespace number, must even and greater than 100.
	 * @param $name Subject namespace name.
	 * @param $talk Talk namespace name.
	 */
	static function setupNamespace( $ns, $name, $talk ) {
		global $wgExtraNamespaces, $wgWikilogNamespaces;

		if ( $ns < 100 ) {
			echo "Wikilog setup: custom namespaces should start ".
				 "at 100 to avoid conflict with standard namespaces.\n";
			die( 1 );
		}
		if ( ($ns % 2) != 0 ) {
			echo "Wikilog setup: given namespace ($ns) is not a ".
				 "subject namespace (even number).\n";
			die( 1 );
		}
		if ( is_array( $wgExtraNamespaces ) && isset( $wgExtraNamespaces[$ns] ) ) {
			$nsname = $wgExtraNamespaces[$ns];
			echo "Wikilog setup: given namespace ($ns) is already " .
				 "set to '$nsname'.\n";
			die( 1 );
		}

		$wgExtraNamespaces[$ns  ] = $name;
		$wgExtraNamespaces[$ns^1] = $talk;
		$wgWikilogNamespaces[] = $ns;
	}

	###
	##  MediaWiki hooks.
	#

	/**
	 * Extension setup function.
	 */
	static function ExtensionInit() {
		global $wgWikilogStylePath, $wgWikilogNamespaces;
		global $wgScriptPath, $wgNamespacesWithSubpages;

		# Set default style path, if not set.
		if ( !$wgWikilogStylePath ) {
			$wgWikilogStylePath = "$wgScriptPath/extensions/Wikilog/style";
		}

		# Find assigned namespaces and make sure they have subpages
		foreach ( $wgWikilogNamespaces as $ns ) {
			$wgNamespacesWithSubpages[$ns  ] = true;
			$wgNamespacesWithSubpages[$ns^1] = true;
		}

		# Work around bug in MediaWiki 1.13 when '?action=render'.
		# https://bugzilla.wikimedia.org/show_bug.cgi?id=15512
		global $wgRequest;
		if ( $wgRequest->getVal( 'action' ) == 'render' ) {
			WikilogParser::expandLocalUrls();
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
	 * SkinTemplateTabs hook handler function.
	 * Adds a wikilog tab to articles in Wikilog namespaces.
	 */
	static function SkinTemplateTabs( &$skin, &$contentActions ) {
		global $wgRequest;

		$wi = self::getWikilogInfo( $skin->mTitle );
		if ( $wi ) {
			$action = $wgRequest->getText( 'action' );
			if ( $wi->isMain() && $skin->mTitle->quickUserCan( 'edit' ) ) {
				$contentActions['wikilog'] = array(
					'class' => ($action == 'wikilog') ? 'selected' : false,
					'text' => wfMsg('wikilog-tab'),
					'href' => $skin->mTitle->getLocalUrl( 'action=wikilog' )
				);
			}
		}
		return true;
	}

	/**
	 * BeforePageDisplay hook handler function.
	 * Adds wikilog CSS to pages displayed.
	 */
	static function BeforePageDisplay( &$output, &$skin ) {
		global $wgWikilogStylePath, $wgStyleVersion;
		$output->addLink( array(
			'rel' => 'stylesheet',
			'href' => $wgWikilogStylePath . '/wikilog.css?' . $wgStyleVersion,
			'type' => 'text/css'
		) );
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
			return NULL;
		}
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
			$this->mItemName = NULL;
			$this->mItemTitle = NULL;
		}
	}

	function isMain() { return $this->mItemTitle === NULL; }
	function isItem() { return $this->mItemTitle !== NULL; }
	function getName() { return $this->mWikilogName; }
	function getTitle() { return $this->mWikilogTitle; }
	function getItemName() { return $this->mItemName; }
	function getItemTitle() { return $this->mItemTitle; }

}

