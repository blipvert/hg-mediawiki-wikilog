<?php
/**
 * Internationalisation file for extension Wikilog.
 *
 * @addtogroup Extensions
 */

$messages = array();

/** English
 * @author Juliano F. Ravasi
 */
$messages['en'] = array(
	# Special:Wikilog
	'wikilog'					=> 'Wikilogs',	# Page title
	'wikilog-specialwikilog'	=> 'Wikilog',	# Special page name
	'wikilog-summary'			=> '',			# Special page summary

	# Extension description
	'wikilog-desc'				=> 'Adds blogging features, creating a wiki-blog hybrid.',

	# Generic strings
	'wikilog-published'			=> 'Published',
	'wikilog-authors'			=> 'Authors',
	'wikilog-wikilog'			=> 'Wikilog',
	'wikilog-title'				=> 'Title',
	'wikilog-actions'			=> 'Actions',

	# Pager strings
	'wikilog-pager-newer-n'		=> '← newer $1',
	'wikilog-pager-older-n'		=> 'older $1 →',
	'wikilog-pager-newest'		=> '⇇ newest',
	'wikilog-pager-oldest'		=> 'oldest ⇉',
	'wikilog-pager-prev'		=> '← previous',
	'wikilog-pager-next'		=> 'next →',
	'wikilog-pager-first'		=> '⇇ first',
	'wikilog-pager-last'		=> 'last ⇉',
	'wikilog-navigation-bar'	=>
		'<div class="wl-navbar visualClear"><div style="float:left">$1 • $2</div>'.
		'<div style="float:right">$3 • $4</div>'.
		'<div style="text-align:center">$5</div></div>',
	'wikilog-pager-empty'		=>
		'<div class="wl-empty">(no items)</div>',

	# Comments page link text
	'wikilog-no-comments'		=> "no comments",
	'wikilog-has-comments'		=> "{{PLURAL:\$1|one comment|\$1 comments}}",

	'wikilog-author-signature'	=> "[[{{ns:User}}:\$1|\$1]] ([[{{ns:User_talk}}:\$1|talk]])",
	'wikilog-item-brief-header'	=> ": ''<small>by \$5, from [[\$1|\$2]], \$6.</small>''",
	'wikilog-item-brief-footer'	=> "",
	'wikilog-item-more'			=> "\n[[\$3|&rarr; continue reading...]]\n",
	'wikilog-item-sub'			=> "",
	'wikilog-item-header'		=> "",
	'wikilog-item-footer'		=> ": ''&mdash; \$5 &#8226; \$6 &#8226; \$7''\n",
	'wikilog-comments-header'	=> "",
	'wikilog-comments-footer'	=> "",

	# Atom and RSS feeds
	'wikilog-feed-title'		=> "{{SITENAME}} - $1 [$2]", # $1 = title, $2 = content language
	'wikilog-feed-description'	=> "Read the most recent posts in this feed.",

	# Warning and error messages
	'wikilog-invalid-author'	=> "Wikilog: Invalid author: \$1.",
	'wikilog-invalid-date'		=> "Wikilog: Invalid date: \$1.",
	'wikilog-invalid-tag'		=> "Wikilog: Invalid tag: \$1.",
	'wikilog-draft-title-mark'	=> "(draft)",
	'wikilog-out-of-context'	=>
			"Warning: Wikilog tags are being used out of context. " .
			"They should only be used in articles in the Wikilog namespace.",
	'wikilog-too-many-authors'	=>
			"Warning: Too many authors listed in this wikilog post.",
	'wikilog-too-many-tags'	=>
			"Warning: Too many tags listed in this wikilog post.",
	'wikilog-reading-draft'		=>
			"<div class='mw-warning'>\n".
			"<center>'''This article is a draft.'''</center>\n\n".
			"This wikilog post is a draft, and is not published yet. ".
			"In order to publish a wikilog post, you have to add the ".
			"<code><nowiki>{{wl-publish:...}}</nowiki></code> tag to the ".
			"article text, optionally providing the publication date and ".
			"one or more authors.\n</div>\n"
);

/** Portuguese (Português)
 * @author Juliano F. Ravasi
 */
$messages['pt'] = array(
	# Special:Wikilog
	'wikilog'					=> 'Wikilogs',
	'wikilog-specialwikilog'	=> 'Wikilog',

	# Extension description
	'wikilog-desc'				=> 'Adiciona recursos de blog, criando um híbrido wiki-blog.',

	# Pager strings
	'wikilog-pager-newer-n'		=> '← $1 próximos',
	'wikilog-pager-older-n'		=> '$1 anteriores →',
	'wikilog-pager-newest'		=> '⇇ mais recentes',
	'wikilog-pager-oldest'		=> 'mais antigos ⇉',
	'wikilog-navigation-bar'	=>
		'<div class="wl-navbar"><div style="float:left">$1 • $2</div>'.
		'<div style="float:right">$3 • $4</div>'.
		'<div style="text-align:center">$5</div></div>',
	'wikilog-pager-empty'		=>
		'<div class="wl-empty">(não há itens)</div>',

	# Comments page link text
	'wikilog-no-comments'		=> "não há comentários",
	'wikilog-has-comments'		=> "{{PLURAL:\$1|um comentário|\$1 comentários}}",
);
