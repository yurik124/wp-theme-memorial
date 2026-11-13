<?php
// Made by tools/block-json.php from blocks/types/*/block.json — do not edit by hand.
return array (
  'book-reviews' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/book-reviews',
    'title' => 'Guest book reviews',
    'category' => 'memorial-reviews',
    'icon' => 'book',
    'description' => 'Reviews left on this site.',
    'keywords' => 
    array (
      0 => 'reviews',
      1 => 'book',
      2 => 'mem_bookreviews',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'limit' => 
      array (
        'type' => 'integer',
        'default' => -1,
      ),
      'title' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'limit' => -1,
        'title' => '',
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'breadcrumbs' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/breadcrumbs',
    'title' => 'Breadcrumbs',
    'category' => 'memorial-layout',
    'icon' => 'arrow-right-alt2',
    'description' => 'Path to the current page; also adds BreadcrumbList structured data.',
    'keywords' => 
    array (
      0 => 'breadcrumbs',
      1 => 'navigation',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'callback-dialog' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/callback-dialog',
    'title' => 'Call me back window',
    'category' => 'memorial-layout',
    'icon' => 'phone',
    'description' => 'Hidden window with the call back form; opens from “Call me back” buttons.',
    'keywords' => 
    array (
      0 => 'call back',
      1 => 'form',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'reusable' => false,
      'multiple' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 800,
    ),
  ),
  'cards' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/cards',
    'title' => 'Section cards',
    'category' => 'memorial-catalog',
    'icon' => 'index-card',
    'description' => 'Cards of the sections of this page (its sub-items in the menu or child pages), of another page or of chosen pages — with photo, product count and starting price.',
    'keywords' => 
    array (
      0 => 'cards',
      1 => 'pages',
      2 => 'mem_cards',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'parent' => 
      array (
        'type' => 'integer',
        'default' => 0,
      ),
      'pages' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'photo' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'number' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'prices' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'caption' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'arrow' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'parent' => 0,
        'pages' => '',
        'photo' => true,
        'number' => true,
        'prices' => true,
        'caption' => true,
        'arrow' => true,
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'catalog' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/catalog',
    'title' => 'Catalog: products of a section',
    'category' => 'memorial-catalog',
    'icon' => 'grid-view',
    'description' => 'Products of a catalog section with section links and sorting. The section is chosen in the block settings; by default — the section of this page.',
    'keywords' => 
    array (
      0 => 'catalog',
      1 => 'products',
      2 => 'mem_catalog',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'cat' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'nav' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'sort' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'heading' => 
      array (
        'type' => 'string',
        'default' => 'h2',
        'enum' => 
        array (
          0 => 'h2',
          1 => 'h3',
        ),
      ),
      'show' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'cat' => '',
        'nav' => true,
        'sort' => true,
        'heading' => 'h2',
        'show' => '',
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'catalog-archive' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/catalog-archive',
    'title' => 'Catalog section with sorting',
    'category' => 'memorial-pages',
    'icon' => 'archive',
    'description' => 'Sub-sections, sorting and products of a catalog section: the one that is open (template) or the one chosen in the block settings.',
    'keywords' => 
    array (
      0 => 'catalog',
      1 => 'category',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'term' => 
      array (
        'type' => 'integer',
        'default' => 0,
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'term' => 0,
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'category-list' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/category-list',
    'title' => 'Catalog sub-sections list',
    'category' => 'memorial-catalog',
    'icon' => 'list-view',
    'description' => 'List of sub-sections of a catalog section with links and starting prices.',
    'keywords' => 
    array (
      0 => 'categories',
      1 => 'catalog',
      2 => 'mem_cat_list',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'cat' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'prices' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'cat' => '',
        'prices' => true,
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'category-photos' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/category-photos',
    'title' => 'Product photos from the catalog',
    'category' => 'memorial-catalog',
    'icon' => 'format-gallery',
    'description' => 'Photo grid of products from catalog sections.',
    'keywords' => 
    array (
      0 => 'photos',
      1 => 'catalog',
      2 => 'mem_cat_photos',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'cat' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'count' => 
      array (
        'type' => 'integer',
        'default' => 6,
      ),
      'random' => 
      array (
        'type' => 'boolean',
        'default' => false,
      ),
      'prices' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'cat' => '',
        'count' => 6,
        'random' => false,
        'prices' => true,
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'cemeteries' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/cemeteries',
    'title' => 'Cemeteries',
    'category' => 'memorial-services',
    'icon' => 'location-alt',
    'description' => 'Cemeteries with addresses, hours and burial status (Memorial → Cemeteries).',
    'keywords' => 
    array (
      0 => 'cemeteries',
      1 => 'places',
      2 => 'mem_cemeteries',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'status' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'filter' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'status' => '',
        'filter' => true,
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'company-details' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/company-details',
    'title' => 'Company details',
    'category' => 'memorial-contacts',
    'icon' => 'businessperson',
    'description' => 'Legal name, taxpayer and registration numbers (Memorial → Contacts).',
    'keywords' => 
    array (
      0 => 'company details',
      1 => 'contacts',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'contacts-badges' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/contacts-badges',
    'title' => 'Opening hours badge',
    'category' => 'memorial-contacts',
    'icon' => 'clock',
    'description' => 'Opening hours of the first address, or the night line when it is set (Memorial → Contacts).',
    'keywords' => 
    array (
      0 => 'hours',
      1 => 'night',
      2 => 'contacts',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'contacts-cards' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/contacts-cards',
    'title' => 'Contact cards',
    'category' => 'memorial-contacts',
    'icon' => 'id-alt',
    'description' => '“Call”, “Write” and the addresses with directions (Memorial → Contacts).',
    'keywords' => 
    array (
      0 => 'contacts',
      1 => 'phone',
      2 => 'address',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'contacts-list' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/contacts-list',
    'title' => 'Contacts list',
    'category' => 'memorial-contacts',
    'icon' => 'id',
    'description' => 'All phones, emails, messengers and addresses with hours (Memorial → Contacts).',
    'keywords' => 
    array (
      0 => 'contacts',
      1 => 'address',
      2 => 'phone',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'contacts-page' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/contacts-page',
    'title' => 'Contacts: the whole page',
    'category' => 'memorial-pages',
    'icon' => 'id',
    'description' => 'Phones, messengers, addresses with routes, maps and company details.',
    'keywords' => 
    array (
      0 => 'contacts',
      1 => 'map',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
      'inserter' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'cookie-banner' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/cookie-banner',
    'title' => 'Cookie banner',
    'category' => 'memorial-layout',
    'icon' => 'privacy',
    'description' => 'Cookie notice or consent request (Memorial → Analytics). Without it, Yandex Metrica in the “after consent” mode never loads.',
    'keywords' => 
    array (
      0 => 'cookie',
      1 => 'consent',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'reusable' => false,
      'multiple' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 800,
    ),
  ),
  'cremation' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/cremation',
    'title' => 'Cremation prices',
    'category' => 'memorial-catalog',
    'icon' => 'editor-table',
    'description' => 'Cremation price table (Memorial → Prices → Cremation).',
    'keywords' => 
    array (
      0 => 'cremation',
      1 => 'prices',
      2 => 'mem_cremation',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'cta' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/cta',
    'title' => 'Call to action',
    'category' => 'memorial-services',
    'icon' => 'megaphone',
    'description' => 'Heading, text and call / message buttons with the site phone.',
    'keywords' => 
    array (
      0 => 'call to action',
      1 => 'phone',
      2 => 'mem_cta',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'title' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'text' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'title' => '',
        'text' => '',
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'faq' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/faq',
    'title' => 'All questions and answers',
    'category' => 'memorial-services',
    'icon' => 'editor-help',
    'description' => 'Questions from all pages (Details blocks) on one page, with search.',
    'keywords' => 
    array (
      0 => 'questions',
      1 => 'FAQ',
      2 => 'mem_faq',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'exclude' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'search' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'popular' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'exclude' => '',
        'search' => true,
        'popular' => '',
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'footer-contacts' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/footer-contacts',
    'title' => 'Footer: addresses and phones',
    'category' => 'memorial-layout',
    'icon' => 'phone',
    'description' => 'Address and phone columns from Memorial → Contacts.',
    'keywords' => 
    array (
      0 => 'footer',
      1 => 'contacts',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'footer-legal' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/footer-legal',
    'title' => 'Footer: copyright and documents',
    'category' => 'memorial-layout',
    'icon' => 'media-document',
    'description' => 'Copyright years, company details, privacy and cookie policy links.',
    'keywords' => 
    array (
      0 => 'footer',
      1 => 'copyright',
      2 => 'privacy',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'guide' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/guide',
    'title' => 'Useful to know',
    'category' => 'memorial-services',
    'icon' => 'welcome-learn-more',
    'description' => 'Article cards with search: child pages of this or another page, or chosen pages.',
    'keywords' => 
    array (
      0 => 'guide',
      1 => 'questions',
      2 => 'mem_guide',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'parent' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'pages' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'all' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'popular' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'parent' => '',
        'pages' => '',
        'all' => true,
        'popular' => '',
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'header-contacts' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/header-contacts',
    'title' => 'Header: phone and call back',
    'category' => 'memorial-layout',
    'icon' => 'phone',
    'description' => 'Main phone number, “Call me back” button and the call icon for phones.',
    'keywords' => 
    array (
      0 => 'header',
      1 => 'phone',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'icon' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/icon',
    'title' => 'Icon in a circle',
    'category' => 'memorial-design',
    'icon' => 'star-empty',
    'description' => 'An icon of the site set (candle, cross, phone, clock…) in a round frame. The icon is chosen in the block settings.',
    'keywords' => 
    array (
      0 => 'icon',
      1 => 'symbol',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'name' => 
      array (
        'type' => 'string',
        'default' => 'candle',
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'name' => 'candle',
      ),
      'viewportWidth' => 400,
    ),
  ),
  'laurel' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/laurel',
    'title' => 'Laurel decoration',
    'category' => 'memorial-design',
    'icon' => 'palmtree',
    'description' => 'Laurel branch, divider, leaf or wreath drawn by the theme.',
    'keywords' => 
    array (
      0 => 'laurel',
      1 => 'divider',
      2 => 'decoration',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'kind' => 
      array (
        'type' => 'string',
        'default' => 'divider',
        'enum' => 
        array (
          0 => 'divider',
          1 => 'branch_plain',
          2 => 'leaf',
          3 => 'wreath',
          4 => 'wreath_berry',
        ),
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'kind' => 'divider',
      ),
      'viewportWidth' => 400,
    ),
  ),
  'link-button' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/link-button',
    'title' => 'Link button',
    'category' => 'memorial-design',
    'icon' => 'button',
    'description' => 'A button in the site style (green, light or outlined) that leads to a page of the site or to any address. The text and the page are set in the block settings on the right.',
    'keywords' => 
    array (
      0 => 'button',
      1 => 'link',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'text' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'page' => 
      array (
        'type' => 'integer',
        'default' => 0,
      ),
      'url' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'variant' => 
      array (
        'type' => 'string',
        'default' => 'light',
        'enum' => 
        array (
          0 => 'gold',
          1 => 'light',
          2 => 'line',
        ),
      ),
      'large' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'arrow' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'text' => '',
        'page' => 0,
        'url' => '',
        'variant' => 'light',
        'large' => true,
        'arrow' => true,
      ),
      'viewportWidth' => 400,
    ),
  ),
  'maps' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/maps',
    'title' => 'Maps',
    'category' => 'memorial-contacts',
    'icon' => 'location-alt',
    'description' => 'Map for each address; loads on a click so the page stays fast.',
    'keywords' => 
    array (
      0 => 'map',
      1 => 'address',
      2 => 'route',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'menu-columns' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/menu-columns',
    'title' => 'Footer: menu in columns',
    'category' => 'memorial-layout',
    'icon' => 'menu',
    'description' => 'Footer menu: items with sub-items become columns, single items go to “Information”.',
    'keywords' => 
    array (
      0 => 'footer',
      1 => 'menu',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'mobile-bar' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/mobile-bar',
    'title' => 'Bottom phone bar on mobiles',
    'category' => 'memorial-layout',
    'icon' => 'smartphone',
    'description' => 'Bottom bar on phones: Call and Message (MAX) or Call me back.',
    'keywords' => 
    array (
      0 => 'mobile',
      1 => 'phone',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'night-line' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/night-line',
    'title' => 'Line “Calls at night”',
    'category' => 'memorial-contacts',
    'icon' => 'clock',
    'description' => 'Note about calls at night (Memorial → Contacts).',
    'keywords' => 
    array (
      0 => 'night',
      1 => 'phone',
      2 => 'hours',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 400,
    ),
  ),
  'not-found' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/not-found',
    'title' => 'Message “Page not found”',
    'category' => 'memorial-pages',
    'icon' => 'warning',
    'description' => '404 message with a call button and catalog sections.',
    'keywords' => 
    array (
      0 => '404',
      1 => 'not found',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'packages' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/packages',
    'title' => 'Funeral packages',
    'category' => 'memorial-catalog',
    'icon' => 'money-alt',
    'description' => 'Funeral package cards with prices (Memorial → Prices).',
    'keywords' => 
    array (
      0 => 'packages',
      1 => 'prices',
      2 => 'mem_packages',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'cremation' => 
      array (
        'type' => 'string',
        'default' => 'auto',
        'enum' => 
        array (
          0 => 'auto',
          1 => 'show',
          2 => 'hide',
        ),
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'cremation' => 'auto',
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'packages-grid' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/packages-grid',
    'title' => 'Funeral packages grid',
    'category' => 'memorial-catalog',
    'icon' => 'money-alt',
    'description' => 'Funeral package cards as on the home page (Memorial → Prices).',
    'keywords' => 
    array (
      0 => 'packages',
      1 => 'prices',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'page-products' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/page-products',
    'title' => 'Products of the page (for the template)',
    'category' => 'memorial-pages',
    'icon' => 'grid-view',
    'description' => 'Catalog tiles or product grid for pages set to show products, with the page text above and below.',
    'keywords' => 
    array (
      0 => 'products',
      1 => 'catalog',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'phone-button' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/phone-button',
    'title' => 'Phone button',
    'category' => 'memorial-contacts',
    'icon' => 'phone',
    'description' => 'Button that calls the main phone number (Memorial → Contacts).',
    'keywords' => 
    array (
      0 => 'phone',
      1 => 'call',
      2 => 'button',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'variant' => 
      array (
        'type' => 'string',
        'default' => 'gold',
        'enum' => 
        array (
          0 => 'gold',
          1 => 'light',
          2 => 'line',
        ),
      ),
      'large' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'variant' => 'gold',
        'large' => true,
      ),
      'viewportWidth' => 400,
    ),
  ),
  'product' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/product',
    'title' => 'Product card',
    'category' => 'memorial-pages',
    'icon' => 'products',
    'description' => 'Photo, price, specifications, call buttons and related products of a product: the one that is open (template) or the one chosen in the block settings.',
    'keywords' => 
    array (
      0 => 'product',
      1 => 'catalog',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'product' => 
      array (
        'type' => 'integer',
        'default' => 0,
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'product' => 0,
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'product-buy' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/product-buy',
    'title' => 'Product: call buttons',
    'category' => 'memorial-pages',
    'icon' => 'phone',
    'description' => '“Call” and “Call me back” buttons with a hint, under the product. For the product template.',
    'keywords' => 
    array (
      0 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'product-media' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/product-media',
    'title' => 'Product: photo',
    'category' => 'memorial-pages',
    'icon' => 'format-image',
    'description' => 'Photo of the product that is open (a laurel wreath when there is no photo). For the product template.',
    'keywords' => 
    array (
      0 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'product-price' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/product-price',
    'title' => 'Product: price',
    'category' => 'memorial-pages',
    'icon' => 'money-alt',
    'description' => 'Price of the product that is open. For the product template.',
    'keywords' => 
    array (
      0 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'product-related' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/product-related',
    'title' => 'Product: similar products',
    'category' => 'memorial-pages',
    'icon' => 'screenoptions',
    'description' => 'Four more products of the same catalog section. For the product template.',
    'keywords' => 
    array (
      0 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'product-specs' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/product-specs',
    'title' => 'Product: specifications',
    'category' => 'memorial-pages',
    'icon' => 'editor-table',
    'description' => 'Specifications and the short description of the product that is open. For the product template.',
    'keywords' => 
    array (
      0 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'restaurants' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/restaurants',
    'title' => 'Memorial meal halls',
    'category' => 'memorial-services',
    'icon' => 'food',
    'description' => 'Halls for memorial meals with photos and an order button (Memorial → Halls).',
    'keywords' => 
    array (
      0 => 'memorial meal',
      1 => 'halls',
      2 => 'mem_restaurants',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'title' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'text' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'title' => '',
        'text' => '',
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'review-buttons' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/review-buttons',
    'title' => 'Yandex review buttons',
    'category' => 'memorial-reviews',
    'icon' => 'external',
    'description' => '“Leave a review” and “We are on Yandex Maps” buttons.',
    'keywords' => 
    array (
      0 => 'reviews',
      1 => 'Yandex',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'review-widget' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/review-widget',
    'title' => 'Yandex reviews widget',
    'category' => 'memorial-reviews',
    'icon' => 'star-filled',
    'description' => 'Official Yandex Maps reviews widget; loads after cookie consent or on a click.',
    'keywords' => 
    array (
      0 => 'reviews',
      1 => 'Yandex',
      2 => 'widget',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'reviews' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/reviews',
    'title' => 'Reviews from review sites',
    'category' => 'memorial-reviews',
    'icon' => 'star-filled',
    'description' => 'Review widgets and links to review sites (Memorial → Reviews).',
    'keywords' => 
    array (
      0 => 'reviews',
      1 => 'rating',
      2 => 'mem_reviews',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'rewio-reviews' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/rewio-reviews',
    'title' => 'Yandex Maps reviews',
    'category' => 'memorial-reviews',
    'icon' => 'star-half',
    'description' => 'Reviews loaded from Yandex Maps by Rewio (Memorial → Rewio reviews).',
    'keywords' => 
    array (
      0 => 'reviews',
      1 => 'Rewio',
      2 => 'mem_rewio',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'count' => 
      array (
        'type' => 'integer',
        'default' => 0,
      ),
      'title' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'cols' => 
      array (
        'type' => 'integer',
        'default' => 0,
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'count' => 0,
        'title' => '',
        'cols' => 0,
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'services' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/services',
    'title' => 'Our services',
    'category' => 'memorial-services',
    'icon' => 'screenoptions',
    'description' => 'Service cards with photos and starting prices (Memorial → Home page → Services).',
    'keywords' => 
    array (
      0 => 'services',
      1 => 'mem_services',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'more' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'more' => '',
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'services-grid' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/services-grid',
    'title' => 'Services grid',
    'category' => 'memorial-services',
    'icon' => 'screenoptions',
    'description' => 'Service cards of the home page (Memorial → Home page → Services), full width.',
    'keywords' => 
    array (
      0 => 'services',
      1 => 'grid',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1000,
    ),
  ),
  'stat' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/stat',
    'title' => 'Figure in a laurel wreath',
    'category' => 'memorial-design',
    'icon' => 'awards',
    'description' => 'Number with a caption in a laurel wreath; the number counts up when it scrolls into view.',
    'keywords' => 
    array (
      0 => 'number',
      1 => 'figure',
      2 => 'wreath',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'number' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'label' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'inserter' => true,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'number' => '',
        'label' => '',
      ),
      'viewportWidth' => 400,
    ),
  ),
  'tagline' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/tagline',
    'title' => 'Tagline: years in business',
    'category' => 'memorial-layout',
    'icon' => 'heart',
    'description' => '“Funeral company in business for over N years” — or the site tagline if the founding year is not set.',
    'keywords' => 
    array (
      0 => 'footer',
      1 => 'years',
      2 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'topbar' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/topbar',
    'title' => 'Line above the header: address and hours',
    'category' => 'memorial-layout',
    'icon' => 'location',
    'description' => 'One of the addresses with its opening hours and the night phone line, above the header. The address is chosen in the block settings.',
    'keywords' => 
    array (
      0 => 'header',
      1 => 'address',
      2 => 'hours',
      3 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'address' => 
      array (
        'type' => 'integer',
        'default' => 0,
      ),
      'hours' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'night' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'reusable' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'address' => 0,
        'hours' => true,
        'night' => true,
      ),
      'viewportWidth' => 1200,
    ),
  ),
  'works' => 
  array (
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 3,
    'name' => 'memorial-plugin/works',
    'title' => 'Our works',
    'category' => 'memorial-services',
    'icon' => 'format-image',
    'description' => 'Completed works with photos, plot sizes and prices: gallery, cards or photos only.',
    'keywords' => 
    array (
      0 => 'works',
      1 => 'portfolio',
      2 => 'photos',
      3 => 'mem_works',
      4 => 'memorial',
    ),
    'textdomain' => 'memorial-plugin',
    'attributes' => 
    array (
      'view' => 
      array (
        'type' => 'string',
        'default' => 'gallery',
        'enum' => 
        array (
          0 => 'gallery',
          1 => 'cards',
          2 => 'photos',
        ),
      ),
      'type' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'works' => 
      array (
        'type' => 'string',
        'default' => '',
      ),
      'count' => 
      array (
        'type' => 'integer',
        'default' => 0,
      ),
      'limit' => 
      array (
        'type' => 'integer',
        'default' => -1,
      ),
      'random' => 
      array (
        'type' => 'boolean',
        'default' => false,
      ),
      'size' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'prices' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'nav' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
      'sort' => 
      array (
        'type' => 'boolean',
        'default' => true,
      ),
    ),
    'supports' => 
    array (
      'autoRegister' => true,
      'html' => false,
      'color' => 
      array (
        'text' => true,
        'background' => true,
        'link' => true,
      ),
      'typography' => 
      array (
        'fontSize' => true,
        'lineHeight' => true,
      ),
      'spacing' => 
      array (
        'margin' => true,
        'padding' => true,
      ),
      '__experimentalBorder' => 
      array (
        'color' => true,
        'radius' => true,
        'style' => true,
        'width' => true,
      ),
      'interactivity' => false,
    ),
    'example' => 
    array (
      'attributes' => 
      array (
        'view' => 'gallery',
        'type' => '',
        'works' => '',
        'count' => 0,
        'limit' => -1,
        'random' => false,
        'size' => true,
        'prices' => true,
        'nav' => true,
        'sort' => true,
      ),
      'viewportWidth' => 1000,
    ),
  ),
);
