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
	public    $mWikilogName;
	public    $mWikilogTitle;
	public    $mItemName;
	public    $mItemTitle;

	private   $mDataLoaded  = false;
	public    $mID          = false;
	public    $mPublish     = false;
	public    $mPubDate     = false;
	public    $mUpdated     = false;
	public    $mAuthors     = array();
	public    $mTags        = array();
	public    $mNumComments = false;

	public function __construct( WikilogInfo &$wi ) {
		$this->mWikilogName = $wi->getName();
		$this->mWikilogTitle = $wi->getTitle();
		$this->mItemName = $wi->getItemName();
		$this->mItemTitle = $wi->getItemTitle();
		$this->mID = $this->mItemTitle->getArticleID();
	}

	public function getID() {
		return $this->mID;
	}

	public function exists() {
		return $this->getID() != 0;
	}

	function getIsPublished() {
		$this->loadData();
		return $this->mPublish;
	}

	function getPublishDate() {
		$this->loadData();
		return $this->mPubDate;
	}

	function getNumComments() {
		$this->loadData();
		$this->updateNumComments();
		return $this->mNumComments;
	}

	public function loadData() {
		if ( !$this->mDataLoaded ) {
			$dbr = wfGetDB( DB_SLAVE );
			$data = $this->itemDataFromId( $dbr, $this->getID() );

			if ( $data ) {
				$this->mParent = $data->wlp_parent;
				$this->mPublish = $data->wlp_publish;
				$this->mPubDate = $data->wlp_pubdate ? wfTimestamp( TS_MW, $data->wlp_pubdate ) : NULL;
				$this->mUpdated = $data->wlp_updated ? wfTimestamp( TS_MW, $data->wlp_updated ) : NULL;
				$this->mNumComments = is_null( $data->wlp_num_comments ) ? false : $data->wlp_num_comments;

				$this->mAuthors = unserialize( $data->wlp_authors );
				if ( !is_array( $this->mAuthors ) ) {
					$this->mAuthors = array();
				}

				$this->mTags = unserialize( $data->wlp_tags );
				if ( !is_array( $this->mTags ) ) {
					$this->mTags = array();
				}
			}

			$this->mDataLoaded = true;
		}
	}

	public function saveData() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->replace(
			'wikilog_posts',
			'wlp_page',
			array(
				'wlp_page'    => $this->mID,
				'wlp_parent'  => $this->mWikilogTitle->getArticleId(),
				'wlp_title'   => $this->mItemName,
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
		$dbw->delete( 'wikilog_posts', array( 'wlp_page' => $this->mItemTitle->getArticleId() ), __METHOD__ );
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
				array( 'wlp_page' => $this->getID() )
			);

			$this->mNumComments = $count;
		}
	}

	public function resetID( $id ) {
		$this->mItemTitle->resetArticleID( $id );
		$this->mID = $id;
	}

	public function getComments( $thread = NULL ) {
		$dbr = wfGetDB( DB_SLAVE );

		$conditions = array( 'wlc_post' => $this->mID );

		if ( $thread ) {
			if ( is_array( $thread ) ) {
				$thread = implode( '/', $thread );
			}
			$thread = $dbr->escapeLike( $thread );
			$conditions[] = "wlc_thread LIKE '{$thread}/%'";
		}

		$result = $dbr->select(
			array(
				'wikilog_comments',
				'page'
			),
			array(
				'wlc_id',
				'wlc_parent',
				'wlc_thread',
				'wlc_user',
				'wlc_user_text',
				'wlc_anon_name',
				'wlc_status',
				'wlc_timestamp',
				'wlc_updated',
				'wlc_comment_page',
				'page_namespace',
				'page_title',
				'page_latest'
			),
			$conditions,
			__METHOD__,
			array(
				'ORDER BY' => 'wlc_thread, wlc_id'
			),
			array(
				'page' => array( 'LEFT JOIN', 'wlc_comment_page = page_id' )
			)
		);
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
	
	private static function itemDataFromConditions( $dbr, $conditions ) {
		$row = $dbr->selectRow(
			'wikilog_posts',
			array(
				'wlp_page',
				'wlp_parent',
				'wlp_publish',
				'wlp_pubdate',
				'wlp_updated',
				'wlp_authors',
				'wlp_tags',
				'wlp_num_comments'
			),
			$conditions,
			__METHOD__
		);
		return $row;
	}

	private static function itemDataFromID( $dbr, $id ) {
		return self::itemDataFromConditions( $dbr, array( 'wlp_page' => $id ) );
	}

}
