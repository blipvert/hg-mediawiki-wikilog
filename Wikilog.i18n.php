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

	# Extension description
	'wikilog-desc'				=> 'Adds blogging features, creating a wiki-blog hybrid.',

	# Generic strings
	'wikilog-published'			=> 'Published',
	'wikilog-draft'				=> 'Draft',
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
			"one or more authors.\n</div>\n",

	# Forms
	'wikilog-form-legend'		=> 'Search for wikilog posts',
	'wikilog-form-wikilog'		=> 'Wikilog:',
	'wikilog-form-category'		=> 'Category:',
	'wikilog-form-author'		=> 'Author:',
	'wikilog-form-tag'			=> 'Tag:',
	'wikilog-form-date'			=> 'Date:',
	'wikilog-form-status'		=> 'Status:',
	'wikilog-show-all'			=> 'All posts',
	'wikilog-show-published'	=> 'Published',
	'wikilog-show-drafts'		=> 'Drafts',

	# Untranslatable strings
	'wikilog-summary'			=> '',			# Special page summary
	'wikilog-navigation-bar'	=>
		'<div class="wl-navbar visualClear"><div style="float:left">$1 • $2</div>'.
		'<div style="float:right">$3 • $4</div>'.
		'<div style="text-align:center">$5</div></div>',
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

	# Generic strings
	'wikilog-published'			=> 'Publicado',
	'wikilog-draft'				=> 'Rascunho',
	'wikilog-authors'			=> 'Autores',
	'wikilog-wikilog'			=> 'Wikilog',
	'wikilog-title'				=> 'Título',
	'wikilog-actions'			=> 'Ações',

	# Pager strings
	'wikilog-pager-newer-n'		=> '← $1 próximos',
	'wikilog-pager-older-n'		=> '$1 anteriores →',
	'wikilog-pager-newest'		=> '⇇ mais recentes',
	'wikilog-pager-oldest'		=> 'mais antigos ⇉',
	'wikilog-pager-prev'		=> '← anterior',
	'wikilog-pager-next'		=> 'próxima →',
	'wikilog-pager-first'		=> '⇇ primeira',
	'wikilog-pager-last'		=> 'última ⇉',
	'wikilog-pager-empty'		=>
		'<div class="wl-empty">(não há itens)</div>',

	# Comments page link text
	'wikilog-no-comments'		=> "não há comentários",
	'wikilog-has-comments'		=> "{{PLURAL:\$1|um comentário|\$1 comentários}}",

	'wikilog-author-signature'	=> "[[{{ns:User}}:\$1|\$1]] ([[{{ns:User_talk}}:\$1|talk]])",
	'wikilog-item-brief-header'	=> ": ''<small>por \$5, em [[\$1|\$2]], \$6.</small>''",
	'wikilog-item-brief-footer'	=> "",
	'wikilog-item-more'			=> "\n[[\$3|&rarr; continuar lendo...]]\n",
	'wikilog-item-sub'			=> "",
	'wikilog-item-header'		=> "",
	'wikilog-item-footer'		=> ": ''&mdash; \$5 &#8226; \$6 &#8226; \$7''\n",
	'wikilog-comments-header'	=> "",
	'wikilog-comments-footer'	=> "",

	# Atom and RSS feeds
	'wikilog-feed-title'		=> "{{SITENAME}} - $1 [$2]", # $1 = title, $2 = content language
	'wikilog-feed-description'	=> "Leia as postagens mais recentes neste feed.",

	# Warning and error messages
	'wikilog-invalid-author'	=> "Wikilog: Autor inválido: \$1.",
	'wikilog-invalid-date'		=> "Wikilog: Data inválida: \$1.",
	'wikilog-invalid-tag'		=> "Wikilog: Rótulo inválido: \$1.",
	'wikilog-draft-title-mark'	=> "(rascunho)",
	'wikilog-out-of-context'	=>
			"Aviso: Rótulos wikilog estão sendo utilizados fora de contexto. " .
			"Eles devem ser usados apenas em artigos no espaço de nomes do Wikilog.",
	'wikilog-too-many-authors'	=>
			"Aviso: Autores demais listados nesta postagem wikilog.",
	'wikilog-too-many-tags'	=>
			"Aviso: Rótulos demais listados nesta postagem wikilog.",
	'wikilog-reading-draft'		=>
			"<div class='mw-warning'>\n".
			"<center>'''Este artigo é um rascunho.'''</center>\n\n".
			"Esta postagem wikilog é um rascunho, e ainda não foi publicado. ".
			"Para publicar uma postagem wikilog, você deve adicionar a tag ".
			"<code><nowiki>{{wl-publish:...}}</nowiki></code> ao texto do ".
			"artigo, fornecendo opcionalmente a data de publicação e um ou ".
			"mais autores.\n</div>\n",

	# Forms
	'wikilog-form-legend'		=> 'Procurar por postagens wikilog',
	'wikilog-form-wikilog'		=> 'Wikilog:',
	'wikilog-form-category'		=> 'Categoria:',
	'wikilog-form-author'		=> 'Autor:',
	'wikilog-form-tag'			=> 'Rótulo:',
	'wikilog-form-date'			=> 'Data:',
	'wikilog-form-status'		=> 'Estado:',
	'wikilog-show-all'			=> 'Todas as postagens',
	'wikilog-show-published'	=> 'Publicados',
	'wikilog-show-drafts'		=> 'Rascunhos',
);
