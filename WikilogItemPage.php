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


class WikilogItemPage extends Article {

	protected $mWikilogName;
	protected $mWikilogTitle;
	protected $mCmtsTitle;
	protected $mNumComments;
	protected $mNumCommentsTxt;
	protected $mItemName;

	private   $mItemDataLoaded = false;
	public    $mItemPublish    = false;
	public    $mItemPubDate    = false;
	public    $mItemAuthors    = array();
	public    $mItemTags       = array();

	function __construct( &$title, &$wi ) {
		parent::__construct( $title );

		wfLoadExtensionMessages( 'Wikilog' );

		$this->mWikilogName = $wi->getName();
		$this->mWikilogTitle =& $wi->getTitle();
		$this->mItemName = $wi->getItemName();
		$this->mCmtsTitle =& Title::makeTitle( $title->getNamespace()^1, $title->getDBkey() );

		# Count comments
		$comments = new WikilogCommentsPage( $this->mCmtsTitle, $wi );
		$this->mNumComments = $comments->getNumComments();
		$this->mNumCommentsTxt =
			( $this->mNumComments == 0 ?
				wfMsg( 'wikilog-no-comments' ) :
				wfMsg( 'wikilog-has-comments', $this->mNumComments ) );
	}

	function view() {
		global $wgOut, $wgUser, $wgContLang;

		# Load data
		$this->loadItemData();

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
		$authors = Wikilog::authorList( array_keys( $this->mItemAuthors ) );
		$pubdate = $wgContLang->timeanddate( $this->mItemPubDate, true );
		$commentsLink = $this->mCmtsTitle->getPrefixedURL();
		$comments = "[[{$commentsLink}|{$this->mNumCommentsTxt}]]";

		# Display draft notice.
		if ( !$this->getIsPublished() ) {
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
	}

	function getIsPublished() {
		$this->loadItemData();
		return $this->mItemPublish;
	}

	function getPublishDate() {
		$this->loadItemData();
		return $this->mItemPubDate;
	}

	private function itemData( $dbr, $conditions ) {
		$row = $dbr->selectRow(
			'wikilog_posts',
			array( 'wlp_page', 'wlp_publish', 'wlp_pubdate', 'wlp_authors', 'wlp_tags' ),
			$conditions,
			__METHOD__
		);
		return $row ;
	}

	private function itemDataFromId( $dbr, $id ) {
		return $this->itemData( $dbr, array( 'wlp_page' => $id ) );
	}

	private function loadItemData() {
		if ( !$this->mItemDataLoaded ) {
			$dbr = $this->getDB();
			$data = $this->itemDataFromId( $dbr, $this->getId() );

			if ( $data ) {
				$this->mItemPublish = $data->wlp_publish;
				$this->mItemPubDate = wfTimestamp( TS_MW, $data->wlp_pubdate );

				$this->mItemAuthors = unserialize( $data->wlp_authors );
				if ( !is_array( $this->mItemAuthors ) ) {
					$this->mItemAuthors = array();
				}

				$this->mItemTags = unserialize( $data->wlp_tags );
				if ( !is_array( $this->mItemTags ) ) {
					$this->mItemTags = array();
				}
			}

			$this->mItemDataLoaded = true;
		}
	}

}

