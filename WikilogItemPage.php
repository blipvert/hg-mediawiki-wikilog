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


class WikilogItemPage extends Article {

	protected $mWikilogName;
	protected $mWikilogTitle;
	protected $mCmtsTitle;
	protected $mNumComments;
	protected $mNumCommentsTxt;
	protected $mItemName;
	protected $mItem;

	function __construct( &$title, &$wi ) {
		parent::__construct( $title );
		wfLoadExtensionMessages( 'Wikilog' );

		$this->mItem = WikilogItem::newFromInfo( $wi );

		$this->mWikilogName = $wi->getName();
		$this->mWikilogTitle = $wi->getTitle();
		$this->mItemName = $this->mItem->mName;

		$this->mCmtsTitle =& Title::makeTitle( MWNamespace::getTalk( $title->getNamespace() ), $title->getDBkey() );

		# Count comments
		$this->mNumComments = $this->mItem->getNumComments();
		$this->mNumCommentsTxt = wfMsgExt(
			( $this->mNumComments ? 'wikilog-has-comments' : 'wikilog-no-comments' ),
			array( 'parseinline', 'parsemag' ), $this->mNumComments );
	}

	function view() {
		global $wgOut, $wgUser, $wgContLang, $wgFeed, $wgWikilogFeedClasses;

		# Get skin
		$skin = $wgUser->getSkin();

		# Set page title
		$fullPageTitle = $this->mItemName.' - '.$this->mWikilogTitle->getPrefixedText();
		$wgOut->setPageTitle( $this->mItemName );
		$wgOut->setHTMLTitle( wfMsg( 'pagetitle', $fullPageTitle ) );

		# Set page subtitle
		$subtitleTxt = wfMsgExt( 'wikilog-item-sub',
			array( 'parse', 'content' ),
			/* $1 */ $this->mWikilogTitle->getPrefixedURL(),
			/* $2 */ $this->mWikilogName
		);
		if ( !empty( $subtitleTxt ) ) {
			$wgOut->setSubtitle( $wgOut->parse( $subtitleTxt ) );
		}

		# Generate some fixed bits.
		$authors = WikilogUtils::authorList( array_keys( $this->mItem->mAuthors ) );
		$pubdate = $wgContLang->timeanddate( $this->mItem->mPubDate, true );

		$commentsLink = $this->mCmtsTitle->getPrefixedURL();
		$comments = "[[{$commentsLink}|{$this->mNumCommentsTxt}]]";

		# Display draft notice.
		if ( !$this->mItem->getIsPublished() ) {
			$wgOut->addHtml( wfMsgWikiHtml( 'wikilog-reading-draft' ) );
		}

		# Item page header.
		$headerTxt = wfMsgExt( 'wikilog-item-header',
			array( 'parse', 'content' ),
			/* $1 */ $this->mWikilogTitle->getPrefixedURL(),
			/* $2 */ $this->mWikilogName,
			/* $3 */ $this->mTitle->getPrefixedURL(),
			/* $4 */ $this->mItemName,
			/* $5 */ $authors,
			/* $6 */ $pubdate,
			/* $7 */ $comments
		);
		if ( !empty( $headerTxt ) ) {
			$wgOut->addHtml( $headerTxt );
		}

		# Display article.
		parent::view();

		# Item page footer.
		$footerTxt = wfMsgExt( 'wikilog-item-footer',
			array( 'parse', 'content' ),
			/* $1 */ $this->mWikilogTitle->getPrefixedURL(),
			/* $2 */ $this->mWikilogName,
			/* $3 */ $this->mTitle->getPrefixedURL(),
			/* $4 */ $this->mItemName,
			/* $5 */ $authors,
			/* $6 */ $pubdate,
			/* $7 */ $comments
		);
		if ( !empty( $footerTxt ) ) {
			$wgOut->addHtml( $footerTxt );
		}

		# Add feed links.
		$links = array();
		if ( $wgFeed ) {
			foreach( $wgWikilogFeedClasses as $format => $class ) {
				$wgOut->addLink( array(
					'rel' => 'alternate',
					'type' => "application/{$format}+xml",
					'title' => wfMsgExt(
						"page-{$format}-feed",
						array( 'content', 'parsemag' ),
						$this->mWikilogTitle->getPrefixedText()
					),
					'href' => $this->mWikilogTitle->getLocalUrl( "feed={$format}" )
				) );
			}
		}
	}

	/**
	 * Override for preSaveTransform. Enables quick post publish by signing
	 * the article using the standard --~~~~ marker. This causes the signature
	 * marker to be replaced by a {{wl-publish:...}} parser function call,
	 * that is then saved to the database and causes the post to be published.
	 */
	function preSaveTransform( $text ) {
		global $wgParser, $wgUser, $wgLocaltimezone;

		$user = $wgUser->getName();
		$popt = ParserOptions::newFromUser( $wgUser );

		$unixts = wfTimestamp( TS_UNIX, $popt->getTimestamp() );
		if ( isset( $wgLocaltimezone ) ) {
			$oldtz = getenv( 'TZ' );
			putenv( "TZ={$wgLocaltimezone}" );
			$date = date( 'Y-m-d H:i:s O', $unixts );
			putenv( "TZ={$oldtz}" );
		} else {
			$date = date( 'Y-m-d H:i:s O', $unixts );
		}

		$sigs = array(
			'/\n?(--)?~~~~~\n?/m' => "\n{{wl-publish: {$date} }}\n",
			'/\n?(--)?~~~~\n?/m' => "\n{{wl-publish: {$date} | {$user} }}\n",
			'/\n?(--)?~~~\n?/m' => "\n{{wl-author: {$user} }}\n"
		);

		$wgParser->startExternalParse( $this->mTitle, $popt, Parser::OT_WIKI );

		$text = $wgParser->replaceVariables( $text );
		$text = preg_replace( array_keys( $sigs ), array_values( $sigs ), $text );
		$text = $wgParser->mStripState->unstripBoth( $text );

		return parent::preSaveTransform( $text );
	}

}

