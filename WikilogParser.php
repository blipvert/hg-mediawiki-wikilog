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
	var $mSummary = false;
	var $mPublish = false;
	var $mPubDate = null;
	var $mAuthors = array();
	var $mTags = array();

	function getAuthors() { return $this->mAuthors; }
	function getTags() { return $this->mTags; }

	static function registerParser( &$parser ) {
		$parser->setHook( 'summary', array( 'WikilogParser', 'summary' ) );
		$parser->setFunctionHook( 'wl-publish', array( 'WikilogParser', 'publish' ), SFH_NO_HASH );
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

	static function summary( $text, $params, $parser ) {
		global $wgParser;

		# Remove extra space to make block rendering easier.
		$text = trim( $text );

		if ( !$wgParser->mExtWikilog->mSummary ) {
			$output = $parser->parse( $text, $parser->getTitle(),
				$parser->getOptions(), true, false );
			$wgParser->mExtWikilog->mSummary = $output->getText();
		}
		return isset( $params['hidden'] ) ? '' : $parser->recursiveTagParse( $text );
	}

	static function publish( &$parser, $pubdate /*, $author... */ ) {
		global $wgWikilogMaxAuthors;
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
				wfLoadExtensionMessages( 'Wikilog' );
				$warning = wfMsg( 'wikilog-invalid-date', $pubdate );
				$parser->mOutput->addWarning( $warning );
			}
		}

		# Remaining arguments are author names
		foreach ( $args as $name ) {
			if ( count( $parser->mExtWikilog->mAuthors ) >= $wgWikilogMaxAuthors ) {
				wfLoadExtensionMessages( 'Wikilog' );
				$warning = wfMsg( 'wikilog-too-many-authors' );
				$parser->mOutput->addWarning( $warning );
				break;
			}

			$user = User::newFromName( $name );
			if ( !is_null( $user ) ) {
				$parser->mExtWikilog->mAuthors[$user->getName()] = $user->getID();
			}
			else {
				wfLoadExtensionMessages( 'Wikilog' );
				$warning = wfMsg( 'wikilog-invalid-author', $name );
				$parser->mOutput->addWarning( $warning );
			}
		}

		return '';
	}

	static function tags( &$parser /*, $tag... */ ) {
		global $wgWikilogMaxTags;
		self::checkNamespace( $parser );

		$tcre = '/[^' . Title::legalChars() . ']/';
		$args = array_slice( func_get_args(), 1 );

		foreach ( $args as $tag ) {
			if ( count( $parser->mExtWikilog->mTags ) >= $wgWikilogMaxTags ) {
				wfLoadExtensionMessages( 'Wikilog' );
				$warning = wfMsg( 'wikilog-too-many-tags' );
				$parser->mOutput->addWarning( $warning );
				break;
			}

			if ( !empty( $tag ) && !preg_match( $tcre, $tag ) ) {
				$parser->mExtWikilog->mTags[$tag] = 1;
			}
			else {
				wfLoadExtensionMessages( 'Wikilog' );
				$warning = wfMsg( 'wikilog-invalid-tag', $tag );
				$parser->mOutput->addWarning( $warning );
			}
		}

		return '';
	}

	static private function checkNamespace( &$parser ) {
		global $wgWikilogNamespaces;
		static $tested = false;

		if ( !$tested ) {
			$title = $parser->getTitle();
			if ( !in_array( $title->getNamespace(), $wgWikilogNamespaces ) ) {
				wfLoadExtensionMessages( 'Wikilog' );
				$warning = wfMsg( 'wikilog-out-of-context' );
				$parser->mOutput->addWarning( $warning );
			}
			$tested = true;
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
