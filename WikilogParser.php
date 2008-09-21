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


/**
 * This class holds the parser functions that hooks into the Parser in order
 * to collect Wikilog metadata, and also stores such data. This class is
 * first attached to the Parser as $parser->mExtWikilog, and then copied to
 * the parser output $popt->mExtWikilog.
 */
class WikilogParser {

	/* Item and Wikilog metadata */
	var $mSummary = false;
	var $mAuthors = array();
	var $mTags = array();

	/* Item metadata */
	var $mPublish = false;
	var $mPubDate = NULL;

	/* Wikilog settings */
	var $mIcon = NULL;
	var $mLogo = NULL;

	function getAuthors() { return $this->mAuthors; }
	function getTags() { return $this->mTags; }

	static function registerParser( &$parser ) {
		$parser->setHook( 'summary', array( 'WikilogParser', 'summary' ) );
		$parser->setFunctionHook( 'wl-settings', array( 'WikilogParser', 'settings' ), SFH_NO_HASH );
		$parser->setFunctionHook( 'wl-publish', array( 'WikilogParser', 'publish' ), SFH_NO_HASH );
		$parser->setFunctionHook( 'wl-author', array( 'WikilogParser', 'author' ), SFH_NO_HASH );
		$parser->setFunctionHook( 'wl-tags', array( 'WikilogParser', 'tags' ), SFH_NO_HASH );
		return true;
	}

	static function clearState( &$parser ) {
		$parser->mExtWikilog = new WikilogParser;

		# Disable TOC in feeds.
		if ( Wikilog::$feedParsing ) {
			$parser->mShowToc = false;
		}
		return true;
	}

	static function beforeInternalParse( &$parser, &$text, &$stripState ) {
		global $wgUser;
		$wi = Wikilog::getWikilogInfo( $parser->getTitle() );

		# Do nothing if it is not a wikilog article.
		if ( !$wi ) return true;

		if ( $wi->isItem() ) {
			# By default, use the item name as the default sort in categories.
			# This can be overriden by {{DEFAULTSORT:...}} if the user wants.
			$parser->setDefaultSort( $wi->getItemName() );
		}

		return true;
	}

	static function afterTidy( &$parser, &$text ) {
		$parser->mOutput->mExtWikilog = $parser->mExtWikilog;
		return true;
	}

	static function summary( $text, $params, &$parser ) {
		# Remove extra space to make block rendering easier.
		$text = trim( $text );

		if ( !$parser->mExtWikilog->mSummary ) {
			$popt = $parser->getOptions();
			$popt->enableLimitReport( false );
			$output = $parser->parse( $text, $parser->getTitle(), $popt, true, false );
			$parser->mExtWikilog->mSummary = $output->getText();
		}
		return isset( $params['hidden'] ) ? '' : $parser->recursiveTagParse( $text );
	}

	static function settings( &$parser /* ... */ ) {
		global $wgOut;
		wfLoadExtensionMessages( 'Wikilog' );
		self::checkNamespace( $parser );

		$args = array();
		foreach ( array_slice( func_get_args(), 1 ) as $arg ) {
			if ( preg_match( '/^([^=]+?)\s*=\s*(.*)/', $arg, $m ) ) {
				$args[$m[1]] = $m[2];
			}
		}

		$icon = $logo = NULL;
		if ( isset( $args['icon'] ) ) {
			if ( ( $icon = self::parseImageLink( $parser, $args['icon'] ) ) ) {
				$parser->mExtWikilog->mIcon = $icon->getTitle();
			}
		}
		if ( isset( $args['logo'] ) ) {
			if ( ( $logo = self::parseImageLink( $parser, $args['logo'] ) ) ) {
				$parser->mExtWikilog->mLogo = $logo->getTitle();
			}
		}
		if ( isset( $args['subtitle'] ) ) {
			$popt = $parser->getOptions();
			$popt->enableLimitReport( false );
			$output = $parser->parse( $args['subtitle'], $parser->getTitle(),
				$popt, true, false );
			$parser->mExtWikilog->mSummary = $output->getText();
		}

		return '';
	}

	static function publish( &$parser, $pubdate /*, $author... */ ) {
		wfLoadExtensionMessages( 'Wikilog' );
		self::checkNamespace( $parser );

		$parser->mExtWikilog->mPublish = true;
		$args = array_slice( func_get_args(), 2 );

		# First argument is the publish date
		if ( !is_null( $pubdate ) ) {
			$ts = strtotime( $pubdate );
			if ( $ts > 0 ) {
				$parser->mExtWikilog->mPubDate = wfTimestamp( TS_MW, $ts );
			}
			else {
				$warning = wfMsg( 'wikilog-invalid-date', $pubdate );
				$parser->mOutput->addWarning( $warning );
			}
		}

		# Remaining arguments are author names
		foreach ( $args as $name ) {
			if ( !self::tryAddAuthor( $parser, $name ) )
				break;
		}

		return '';
	}

	static function author( &$parser /*, $author... */ ) {
		wfLoadExtensionMessages( 'Wikilog' );
		self::checkNamespace( $parser );

		$args = array_slice( func_get_args(), 1 );
		foreach ( $args as $name ) {
			if ( !self::tryAddAuthor( $parser, $name ) )
				break;
		}
		return '';
	}

	static function tags( &$parser /*, $tag... */ ) {
		wfLoadExtensionMessages( 'Wikilog' );
		self::checkNamespace( $parser );

		$args = array_slice( func_get_args(), 1 );
		foreach ( $args as $tag ) {
			if ( !self::tryAddTag( $parser, $tag ) )
				break;
		}
		return '';
	}

	/**
	 * Adds an author to the current article. If too many authors, warns.
	 * @return False on overflow, true otherwise.
	 */
	private static function tryAddAuthor( &$parser, $name ) {
		global $wgWikilogMaxAuthors;

		if ( count( $parser->mExtWikilog->mAuthors ) >= $wgWikilogMaxAuthors ) {
			$warning = wfMsg( 'wikilog-too-many-authors' );
			$parser->mOutput->addWarning( $warning );
			return false;
		}

		$user = User::newFromName( $name );
		if ( !is_null( $user ) ) {
			$parser->mExtWikilog->mAuthors[$user->getName()] = $user->getID();
		}
		else {
			$warning = wfMsg( 'wikilog-invalid-author', $name );
			$parser->mOutput->addWarning( $warning );
		}
		return true;
	}

	/**
	 * Adds a tag to the current article. If too many tags, warns.
	 * @return False on overflow, true otherwise.
	 */
	private static function tryAddTag( &$parser, $tag ) {
		global $wgWikilogMaxTags;

		static $tcre = false;
		if ( !$tcre ) { $tcre = '/[^' . Title::legalChars() . ']/'; }

		if ( count( $parser->mExtWikilog->mTags ) >= $wgWikilogMaxTags ) {
			$warning = wfMsg( 'wikilog-too-many-tags' );
			$parser->mOutput->addWarning( $warning );
			return false;
		}

		if ( !empty( $tag ) && !preg_match( $tcre, $tag ) ) {
			$parser->mExtWikilog->mTags[$tag] = 1;
		}
		else {
			$warning = wfMsg( 'wikilog-invalid-tag', $tag );
			$parser->mOutput->addWarning( $warning );
		}
		return true;
	}

	/**
	 * Check if the calling parser function is being executed in Wikilog
	 * context. Generates a parser warning if it isn't.
	 */
	private static function checkNamespace( &$parser ) {
		global $wgWikilogNamespaces;
		static $tested = false;

		if ( !$tested ) {
			$title = $parser->getTitle();
			if ( !in_array( $title->getNamespace(), $wgWikilogNamespaces ) ) {
				$warning = wfMsg( 'wikilog-out-of-context' );
				$parser->mOutput->addWarning( $warning );
			}
			$tested = true;
		}
	}

	/**
	 * Parses an image link.
	 * Wrapper around parseMediaLink() that only returns images. Parser
	 * warnings are generated if the file is not an image, or if it is
	 * invalid.
	 *
	 * @return File instance, or NULL.
	 */
	private static function parseImageLink( &$parser, $text ) {
		$obj = self::parseMediaLink( $parser, $text );
		if ( !$obj ) {
			$warning = wfMsg( 'wikilog-invalid-file', htmlspecialchars( $text ) );
			$parser->mOutput->addWarning( $warning );
			return NULL;
		}

		list( $t1, $t2, $file ) = $obj;
		if ( !$file ) {
			$warning = wfMsg( 'wikilog-file-not-found', htmlspecialchars( $t1 ) );
			$parser->mOutput->addWarning( $warning );
			return NULL;
		}

		$type = $file->getMediaType();
		if ( $type != MEDIATYPE_BITMAP && $type != MEDIATYPE_DRAWING ) {
			$warning = wfMsg( 'wikilog-not-an-image', $file->getName() );
			$parser->mOutput->addWarning( $warning );
			return NULL;
		}

		return $file;
	}

	/**
	 * Parses a media link.
	 * This is a very small subset of Parser::replaceInternalLinks() that
	 * parses a single image or media link, and returns the parsed text,
	 * as well as a File instance of the referenced media, if available.
	 *
	 * @return Three-element array containing the matched parts of the link,
	 *   and the file object, or NULL.
	 */
	private static function parseMediaLink( &$parser, $text ) {
		$tc = Title::legalChars();
		if ( !preg_match( "/\\[\\[([{$tc}]+)(?:\\|(.+?))?]]/", $text, $m ) )
			return NULL;

		$nt = Title::newFromText( $m[1] );
		if ( !$nt )
			return NULL;

		$ns = $nt->getNamespace();
		if ( $ns == NS_IMAGE || $ns == NS_MEDIA ) {
			$parser->mOutput->addLink( $nt );
			return @ array( $m[1], $m[2], wfFindFile( $nt ) );
		} else {
			return NULL;
		}
	}

}


/**
 * Since wikilog parses articles with specific options in order to be
 * outputted in feeds, it is necessary to store these parsed outputs in
 * the cache separatelly. This derived class from ParserCache overloads the
 * getKey() function in order to provide a specific namespace for this
 * purpose.
 */
class WikilogParserCache extends ParserCache {

	public static function &singleton() {
		static $instance;
		if ( !isset( $instance ) ) {
			global $parserMemc;
			$instance = new WikilogParserCache( $parserMemc );
		}
		return $instance;
	}

	function getKey( &$article, &$user ) {
		$pageid = intval( $article->getID() );
		$hash = $user->getPageRenderingHash();
		$key = wfMemcKey( 'wlcache', 'idhash', "$pageid-$hash" );
		return $key;
	}

}
