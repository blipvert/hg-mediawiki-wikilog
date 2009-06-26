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


/**
 * Utilitary functions used by the Wikilog extension.
 */
class WikilogUtils {

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
	public static function parsedArticle( Title $title, $feed = false, &$parser = NULL ) {
		global $wgUser, $wgEnableParserCache;

		$article = new Article( $title );

		// First try the parser cache.
		if ( $wgEnableParserCache ) {
			// Select parser cache according to the $feed flag.
			$parserCache = $feed
				? WikilogParserCache::singleton()
				: ParserCache::singleton();

			// Look for the parsed article output in the parser cache.
			$parserOutput = $parserCache->get( $article, $wgUser );

			// On success, return the object retrieved from the cache.
			if ( $parserOutput ) {
				return array( $article, $parserOutput );
			}
		}

		// Now we know we will have to call the parser.

		// Parser options.
		$parserOpt = ParserOptions::newFromUser( $wgUser );
		$parserOpt->setTidy( true );

		// Enable some feed-specific behavior.
		if ( $feed ) {
			$saveFeedParse = WikilogParser::enableFeedParsing();
			$saveExpUrls = WikilogParser::expandLocalUrls();
			$parserOpt->setEditSection( false );
		} else {
			$parserOpt->enableLimitReport();
		}

		// Get a parser instance, if not already provided by the caller.
		if ( is_null( $parser ) ) {
			global $wgParser;
			$parser = clone $wgParser;
			$parser->startExternalParse( $title, $parserOpt, Parser::OT_HTML );
		}

		// Parse article.
		$arttext = $article->fetchContent();
		$parserOutput = $parser->parse( $arttext, $title, $parserOpt );

		// Save in parser cache.
		if ( $wgEnableParserCache && $parserOutput->getCacheTime() != -1 ) {
			$parserCache->save( $parserOutput, $article, $wgUser );
		}

		// Restore default behavior.
		if ( $feed ) {
			WikilogParser::enableFeedParsing( $saveFeedParse );
			WikilogParser::expandLocalUrls( $saveExpUrls );
		}

		return array( $article, $parserOutput );
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
	public static function authorList( $list ) {
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
				$first = implode( ', ', array_map( array( 'Wikilog', 'authorSig' ),
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
	public static function authorSig( $author ) {
		static $authorSigCache = array();
		if ( !isset( $authorSigCache[$author] ) )
			$authorSigCache[$author] = wfMsgForContent( 'wikilog-author-signature', $author );
		return $authorSigCache[$author];
	}

	/**
	 * Split summary of a wikilog post from the contents.
	 * If summary was provided in <summary>...</summary> tags, use it,
	 * otherwise, use some heuristics to find it in the content.
	 */
	public static function splitSummaryContent( $parserOutput ) {
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
					$summary = NULL;
				}
			} else {
				# Short article, no summary.
				$summary = NULL;
			}
		}

		return array( $summary, $content );
	}

	/**
	 * Formats a comments page link.
	 *
	 * @param $item WikilogItem object.
	 * @return Wikitext-formatted comments link.
	 */
	public static function getCommentsWikiText( WikilogItem &$item ) {
		$commentsNum = $item->getNumComments();
		$commentsMsg = ( $commentsNum ? 'wikilog-has-comments' : 'wikilog-no-comments' );
		$commentsUrl = $item->mTitle->getTalkPage()->getPrefixedURL();
		$commentsTxt = wfMsgExt( $commentsMsg, array( 'parsemag', 'content' ), $commentsNum );
		return "[[{$commentsUrl}|{$commentsTxt}]]";
	}

	/**
	 * Causes an update to the given Wikilog main page.
	 */
	public static function updateWikilog( $title ) {
		if ( $title->exists() ) {
			$title->invalidateCache();
			$title->purgeSquid();

			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'wikilog_wikilogs',
				array( 'wlw_updated' => $dbw->timestamp() ),
				array( 'wlw_page' => $title->getArticleId(), ),
				__METHOD__
			);
		}
	}

	/**
	 * Given a MagicWord, returns any array element which key matches the
	 * magic word. Always case-sensitive.
	 */
	public static function arrayMagicKeyGet( &$array, MagicWord $mw ) {
		foreach ( $mw->getSynonyms() as $key ) {
			if ( array_key_exists( $key, $array ) )
				return $array[$key];
		}
		return NULL;
	}

	/**
	 * Builds an HTML form in a table.
	 */
	public static function buildForm( $fields ) {
		$rows = array();
		foreach ( $fields as $field ) {
			if ( is_array( $field ) ) {
				$row = Xml::tags( 'td', array( 'class' => 'mw-label' ), $field[0] ).
					Xml::tags( 'td', array( 'class' => 'mw-input' ), $field[1] );
			} else {
				$row = Xml::tags( 'td', array( 'class' => 'mw-input',
					'colspan' => 2 ), $field );
			}
			$rows[] = Xml::tags( 'tr', array(), $row );
		}
		$form = Xml::tags( 'table', array( 'width' => '100%' ),
			implode( "\n", $rows ) );
		return $form;
	}

}
