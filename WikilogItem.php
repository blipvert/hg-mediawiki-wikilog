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


class WikilogItem {

	public    $mID          = false;
	public    $mName        = false;
	public    $mTitle       = false;
	public    $mParent      = false;
	public    $mParentName  = false;
	public    $mParentTitle = false;
	public    $mPublish     = false;
	public    $mPubDate     = false;
	public    $mUpdated     = false;
	public    $mAuthors     = array();
	public    $mTags        = array();
	public    $mNumComments = false;

	public function __construct( ) {
	}

	public function getID() {
		return $this->mID;
	}

	public function exists() {
		return $this->getID() != 0;
	}

	public function getIsPublished() {
		return $this->mPublish;
	}

	public function getPublishDate() {
		return $this->mPubDate;
	}

	public function getUpdatedDate() {
		return $this->mUpdated;
	}

	public function getNumComments() {
		$this->updateNumComments();
		return $this->mNumComments;
	}

	public function saveData() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->replace(
			'wikilog_posts',
			'wlp_page',
			array(
				'wlp_page'    => $this->mID,
				'wlp_parent'  => $this->mParent,
				'wlp_title'   => $this->mName,
				'wlp_publish' => $this->mPublish,
				'wlp_pubdate' => $this->mPubDate ? $dbw->timestamp( $this->mPubDate ) : '',
				'wlp_updated' => $this->mUpdated ? $dbw->timestamp( $this->mUpdated ) : '',
				'wlp_authors' => serialize( $this->mAuthors ),
				'wlp_tags'    => serialize( $this->mTags ),
			),
			__METHOD__
		);
	}

	public function deleteData() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'wikilog_posts', array( 'wlp_page' => $this->getID() ), __METHOD__ );
	}

	public function updateNumComments( $force = false ) {
		if ( $force || $this->mNumComments === false ) {
			$dbw = wfGetDB( DB_MASTER );

			# Retrieve estimated number of comments
			$count = $dbw->selectField( 'wikilog_comments', 'COUNT(*)',
				array( 'wlc_post' => $this->getID() ), __METHOD__ );

			# Update wikilog_posts cache
			$dbw->update( 'wikilog_posts',
				array( 'wlp_num_comments' => $count ),
				array( 'wlp_page' => $this->getID() ),
				__METHOD__
			);

			$this->mNumComments = $count;
		}
	}

	public function resetID( $id ) {
		$this->mTitle->resetArticleID( $id );
		$this->mID = $id;
	}

	public function getComments( $thread = NULL ) {
		$dbr = wfGetDB( DB_SLAVE );

		if ( $thread ) {
			$result = WikilogComment::fetchAllFromItemThread( $dbr, $this->mID, $thread );
		} else {
			$result = WikilogComment::fetchAllFromItem( $dbr, $this->mID );
		}

		$comments = array();
		foreach( $result as $row ) {
			$comment = WikilogComment::newFromRow( $this, $row );
			if ( $row->page_latest ) {
				$rev = Revision::newFromId( $row->page_latest );
				$comment->setText( $rev->getText() );
			}
			$comments[] = $comment;
		}
		$result->free();
		return $comments;
	}

	public static function newFromRow( $row ) {
		$item = new WikilogItem();
		$item->mID          = intval( $row->wlp_page );
		$item->mName        = strval( $row->wlp_title );
		$item->mTitle       = Title::makeTitle( $row->page_namespace, $row->page_title );
		$item->mParent      = intval( $row->wlp_parent );
		$item->mParentName  = str_replace( '_', ' ', $row->wlw_title );
		$item->mParentTitle = Title::makeTitle( $row->wlw_namespace, $row->wlw_title );
		$item->mPublish     = intval( $row->wlp_publish );
		$item->mPubDate     = $row->wlp_pubdate ? wfTimestamp( TS_MW, $row->wlp_pubdate ) : NULL;
		$item->mUpdated     = $row->wlp_updated ? wfTimestamp( TS_MW, $row->wlp_updated ) : NULL;
		$item->mNumComments = is_null( $row->wlp_num_comments ) ? false : $row->wlp_num_comments;
		$item->mAuthors     = unserialize( $row->wlp_authors );
		$item->mTags        = unserialize( $row->wlp_tags );
		if ( !is_array( $item->mAuthors ) ) {
			$item->mAuthors = array();
		}
		if ( !is_array( $item->mTags ) ) {
			$item->mTags = array();
		}
		return $item;
	}

	public static function newFromID( $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		$row = self::loadFromID( $dbr, $id );
		if ( $row ) {
			return self::newFromRow( $row );
		}
		return NULL;
	}

	public static function newFromInfo( WikilogInfo &$wi ) {
		$itemTitle = $wi->getItemTitle();
		if ( $itemTitle ) {
			return self::newFromID( $itemTitle->getArticleID() );
		} else {
			return NULL;
		}
	}

	private static function loadFromConds( $dbr, $conds ) {
		extract( self::selectInfo( $dbr ) );	// $tables, $fields
		extract( $dbr->tableNames( 'page' ) );
		$row = $dbr->selectRow( $tables, $fields, $conds, __METHOD__, array( ) );
		return $row;
	}

	private static function loadFromID( $dbr, $id ) {
		return self::loadFromConds( $dbr, array( 'wlp_page' => $id ) );
	}

	private static function selectInfo( $dbr ) {
		extract( $dbr->tableNames( 'wikilog_posts', 'page' ) );
		return array(
			'tables' =>
				"{$wikilog_posts} ".
				"LEFT JOIN {$page} AS w ON (w.page_id = wlp_parent) ".
				"LEFT JOIN {$page} AS p ON (p.page_id = wlp_page) ",
			'fields' => array(
				'wlp_page',
				'wlp_parent',
				'w.page_namespace AS wlw_namespace',
				'w.page_title AS wlw_title',
				'p.page_namespace AS page_namespace',
				'p.page_title AS page_title',
				'wlp_title',
				'wlp_publish',
				'wlp_pubdate',
				'wlp_updated',
				'wlp_authors',
				'wlp_tags',
				'wlp_num_comments'
			)
		);
	}

}
