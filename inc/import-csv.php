<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Lecture et validation des fichiers CSV d'import.
 *
 * EXI-ARCH-03 : extrait de inc/admin-bulk-import.php. Regroupe la validation
 * du fichier recu, le decoupage en sections et le modele telechargeable.
 *
 * Les garde-fous sont conserves a l'identique : taille maximale, extension,
 * lisibilite, plafond de 500 lignes, retrait du BOM des exports Excel.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
// 4. TRAITEMENT DE L'IMPORT
// ============================================================================
/**
 * Valide le fichier reçu et renvoie son contenu, ou une erreur.
 *
 * EXI-ARCH-03 : extrait de swiftboard_process_import(), qui faisait 231
 * lignes et enchaînait validation, parsing, création des sujets puis des
 * réponses. Chaque garde-fou y était noyé.
 *
 * Les contrôles sont volontairement conservés à l'identique — taille,
 * extension, lisibilité, volume — ils protègent le serveur d'un import
 * hors gabarit.
 *
 * @param array<string, mixed>             $file  Entrée de $_FILES.
 * @param array<int, array<string, mixed>> $log   Journal, modifié par référence.
 * @return string|false Contenu du fichier, ou false si refusé.
 */
function swiftboard_import_valider_fichier( $file, array &$log ) {
	if ( $file['error'] !== UPLOAD_ERR_OK ) {
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'msg'   => '❌ Erreur upload : ' . $file['error'],
			'error' => true,
		);
		return false;
	}
	if ( $file['size'] > 5 * 1024 * 1024 ) {
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'msg'   => '❌ Fichier trop volumineux (max 5 MB)',
			'error' => true,
		);
		return false;
	}

	$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'csv', 'xlsx', 'txt' ), true ) ) {
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'msg'   => '❌ Format non supporté. Utilisez CSV ou XLSX.',
			'error' => true,
		);
		return false;
	}
	// v5.3.5 — EXI-IMPORT-01 : le .xlsx est lu NATIVELEMENT (le client gère
	// ses membres/sujets dans Excel ; plus d'allers-retours « Fichier >
	// Exporter > CSV »). On convertit la première feuille en texte CSV qui
	// suit ensuite le pipeline identique et éprouvé.
	if ( $ext === 'xlsx' ) {
		$rows = swiftboard_import_xlsx_read_rows( $file['tmp_name'], $log );
		if ( $rows === false ) {
			return false;
		}
		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'msg'     => '📗 Excel lu nativement : ' . count( $rows ) . ' lignes',
			'success' => true,
		);
		return swiftboard_import_rows_to_csv( $rows );
	}

	$content = file_get_contents( $file['tmp_name'] );
	if ( $content === false ) {
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'msg'   => '❌ Impossible de lire le fichier',
			'error' => true,
		);
		return false;
	}

	// BOM UTF-8 des exports Excel : sans ce retrait, le premier en-tete de
	// colonne devient « \xEF\xBB\xBFforum » et n'est jamais reconnu.
	if ( substr( $content, 0, 3 ) === "\xEF\xBB\xBF" ) {
		$content = substr( $content, 3 );
	}

	return $content;
}

/**
 * Découpe le CSV en sections « topics » et « replies ».
 *
 * @param string                           $content Contenu CSV, BOM déjà retiré.
 * @param array<int, array<string, mixed>> $log     Journal, modifié par référence.
 * @return array<string, array<int, array<string, string>>>|false Sections « topics » et
 *                 « replies », ou false si le fichier est refuse.
 */
function swiftboard_import_parser_sections( $content, array &$log ) {
	$lines = swiftboard_str_getcsv_all( $content );
	$log[] = array(
		'time' => current_time( 'mysql' ),
		'msg'  => '📄 ' . count( $lines ) . ' lignes lues',
	);

	if ( count( $lines ) > 500 ) {
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'msg'   => '❌ Trop de lignes (max 500). Découpez votre fichier.',
			'error' => true,
		);
		return false;
	}

	$sections        = array(
		'topics'  => array(),
		'replies' => array(),
		'membres' => array(),
		'blog'    => array(),
	);
	$current_section = 'topics';
	$headers         = array(
		'topics'  => null,
		'replies' => null,
		'membres' => null,
		'blog'    => null,
	);

	foreach ( $lines as $line ) {
		if ( empty( $line ) || ( count( $line ) === 1 && trim( $line[0] ) === '' ) ) {
			continue;
		}
		if ( count( $line ) === 1 && trim( $line[0] ) === '---REPLIES---' ) {
			$current_section = 'replies';
			continue;
		}
		// v5.3.5 — EXI-IMPORT-02 : section dédiée aux membres + grades
		// (« le fichier excel avec le rank de chacun des membres »).
		if ( count( $line ) === 1 && trim( $line[0] ) === '---MEMBRES---' ) {
			$current_section = 'membres';
			continue;
		}
		// v5.3.5-bis : ---TOPICS--- marqueur explicite RETOUR à la section
		// sujets après ---MEMBRES--- ; sinon la ligne d'en-têtes sujets était
		// lue comme DONNÉE membres et rejetée (« 8 colonnes pour 4 en-têtes »,
		// scénario E2E réel). L'heuristique ci-dessous rend le marqueur
		// optionnel dans les fichiers Excel du client.
		if ( count( $line ) === 1 && trim( $line[0] ) === '---TOPICS---' ) {
			$current_section = 'topics';
			continue;
		}
		if ( count( $line ) === 1 && trim( $line[0] ) === '---BLOG---' ) {
			$current_section = 'blog';
			continue;
		}
		// Auto-détection d'en-têtes : une ligne contenant les colonnes
		// signatures d'une section en BASCULE la lecture, même sans marqueur.
		$norm = array_map(
			function ( $c ) {
				return strtolower( trim( (string) $c ) );
			},
			$line
		);
		if ( in_array( 'title', $norm, true ) && in_array( 'forum', $norm, true ) && 'topics' !== $current_section ) {
			$current_section   = 'topics';
			$headers['topics'] = null;
		} elseif ( in_array( 'topic_title', $norm, true ) && 'replies' !== $current_section ) {
			$current_section    = 'replies';
			$headers['replies'] = null;
		} elseif ( in_array( 'identifiant', $norm, true ) && in_array( 'email', $norm, true ) && 'membres' !== $current_section ) {
			$current_section    = 'membres';
			$headers['membres'] = null;
		} elseif ( in_array( 'blog_title', $norm, true ) && 'blog' !== $current_section ) {
			$current_section  = 'blog';
			$headers['blog']  = null;
		}
		if ( $headers[ $current_section ] === null ) {
			$headers[ $current_section ] = array_map( 'trim', array_map( 'strtolower', $line ) );
			$log[]                       = array(
				'time' => current_time( 'mysql' ),
				'msg'  => '📋 Headers ' . $current_section . ' : ' . implode( ', ', $headers[ $current_section ] ),
			);
			continue;
		}
		// EXI-ARCH-04 : array_pad() ne COMPLETE que les lignes trop courtes ;
		// une ligne plus LONGUE que l'en-tete (virgule non echappee dans un
		// titre, cas courant) la traversait intacte. En PHP 7 array_combine()
		// renvoyait false et la ligne etait ignoree ; depuis PHP 8.0 elle leve
		// une ValueError FATALE, en plein milieu d'un import qui a deja cree
		// des sujets — et sans avoir enregistre de quoi les annuler.
		//
		// La ligne est REJETEE, pas tronquee : au-dela de l'en-tete, les
		// colonnes sont decalees et l'enregistrement serait importe avec des
		// valeurs fausses (l'auteur prend le fragment de contenu, la date se
		// vide). Mieux vaut une ligne signalee qu'un sujet corrompu — c'est
		// aussi ce que faisait le code d'origine sous PHP 7.
		$attendu = count( $headers[ $current_section ] );

		if ( count( $line ) !== $attendu ) {
			if ( count( $line ) > $attendu ) {
				$log[] = array(
					'time'  => current_time( 'mysql' ),
					'msg'   => '⚠️ Ligne ignorée : ' . count( $line ) . ' colonnes pour '
						. $attendu . ' en-têtes (virgule non échappée dans un champ ?).',
					'error' => true,
				);
				continue;
			}
			// Ligne plus courte : complétée, le decalage est impossible.
			$line = array_pad( $line, $attendu, '' );
		}

		$sections[ $current_section ][] = array_combine( $headers[ $current_section ], $line );
	}

	$log[] = array(
		'time' => current_time( 'mysql' ),
		'msg'  => '📊 ' . count( $sections['membres'] ) . ' membres + ' . count( $sections['topics'] ) . ' topics + ' . count( $sections['replies'] ) . ' replies + ' . count( $sections['blog'] ) . ' articles blog à importer',
	);

	return $sections;
}

// ============================================================================
// 5. HELPERS
/**
 * swiftboard_str_getcsv_all().
 *
 * @param string $content Contenu à traiter.
 * @return mixed
 */
function swiftboard_str_getcsv_all( $content ) {
	$lines     = array();
	$current   = '';
	$in_quotes = false;
	$len       = strlen( $content );

	for ( $i = 0; $i < $len; $i++ ) {
		$char = $content[ $i ];
		$next = ( $i + 1 < $len ) ? $content[ $i + 1 ] : '';

		if ( $char === '"' ) {
			if ( $in_quotes && $next === '"' ) {
				$current .= '""';
				++$i;
			} else {
				$in_quotes = ! $in_quotes;
				$current  .= $char;
			}
		} elseif ( ( $char === "\n" || $char === "\r" ) && ! $in_quotes ) {
			if ( $char === "\r" && $next === "\n" ) {
				++$i;
			}
			if ( $current !== '' ) {
				$lines[] = str_getcsv( $current, ',', '"', '\\' );
				$current = '';
			}
		} else {
			$current .= $char;
		}
	}
	if ( $current !== '' ) {
		$lines[] = str_getcsv( $current, ',', '"', '\\' );
	}
	return $lines;
}

// ============================================================================
// 7. TEMPLATE TÉLÉCHARGEABLE
// ============================================================================
/**
 * @return void
 */
function swiftboard_download_template() {
	// v5.3.6 — trois modeles : complet / membres (rangs+karma) / suite de
	// commentaires (reponses vers sujets DEJA existants).
	$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'complet';
	if ( ! in_array( $type, array( 'complet', 'membres', 'suite' ), true ) ) {
		$type = 'complet';
	}

	if ( 'membres' === $type ) {
		$filename = 'swiftboard-membres-grades-karma.csv';
		$csv      = "---MEMBRES---\n";
		$csv     .= "identifiant,email,grade,mot_de_passe,karma,avatar\n";
		$csv     .= "karim.benali,karim.benali@exemple.fr,vip,,1280,3\n";
		$csv     .= "sophie.martin,sophie.martin@exemple.fr,member,SophieMotdepasse12!,320,\n";
		$csv     .= "dr.petit,dr.petit@exemple.fr,moderator,,2400,https://exemple.com/photo.jpg\n";
		$csv     .= "ancien.membre,ancien.membre@exemple.fr,member,,450\n";
	} elseif ( 'suite' === $type ) {
		$filename = 'swiftboard-reponses-suite-sujets.csv';
		$csv      = "---REPLIES---\n";
		$csv     .= "topic_title,content,author,grade,votes,reply_to,date\n";
		$csv     .= "TITRE EXACT D'UN SUJET EXISTANT 1,\"Ce commentaire sera AJOUTE a la suite du sujet deja commence sur le forum (aucun sujet recree).\",sophie.martin,member,2,,2026-08-01\n";
		$csv     .= "TITRE EXACT D'UN SUJET EXISTANT 1,\"Reponse imbriquee au precedent : mettez en reply_to le nom d'auteur du commentaire vise.\",dr.petit,pro,5,sophie.martin,2026-08-01\n";
		$csv     .= "TITRE EXACT D'UN SUJET EXISTANT 2,\"La date conserve celle d'origine pour un rendu naturel. L'auteur inconnu est cree a la volee.\",ancien.membre,member,1,,2026-07-28\n";
	} else {
		$filename = 'swiftboard-import-template.csv';
		// v5.3.6 : colonne karma dans la section membres.
		$csv  = "---MEMBRES---\n";
		$csv .= "identifiant,email,grade,mot_de_passe,karma,avatar\n";
		$csv .= "karim.benali,karim.benali@exemple.fr,vip,,1280,3\n";
		$csv .= "sophie.martin,sophie.martin@exemple.fr,member,SophieMotdepasse12!,320,\n";
		$csv .= "dr.petit,dr.petit@exemple.fr,moderator,,2400,https://exemple.com/photo.jpg\n";
		$csv .= "\n";
		$csv .= "---TOPICS---\n";
		$csv .= "forum,title,content,author,grade,image_url,votes,vues,date\n";
		$csv .= "Annonces & News,Bienvenue sur SwiftBoard !,\"Bonjour à tous ! Ce forum est propulsé par SwiftBoard avec un design inspiré de Reddit. N'hésitez pas à partager vos retours.\",Marie Dupont,vip,https://via.placeholder.com/800x450,15,,2026-07-10\n";
		$csv .= "Discussion générale,Quel éditeur de code utilisez-vous en 2026 ?,\"VSCode, WebStorm, Cursor, Zed ? Donnez vos avis !\",Alex Chen,,,42,,2026-07-12\n";
		$csv .= "\n";
		$csv .= "---REPLIES---\n";
		$csv .= "topic_title,content,author,grade,votes,reply_to,date\n";
		$csv .= "Bienvenue sur SwiftBoard !,Merci pour ce forum ! Le design bleu est superbe.,Alice Lambert,member,3,,2026-07-10\n";
		$csv .= "Bienvenue sur SwiftBoard !,Comment on active le dark mode ?,Bob Moreau,1,Alice Lambert,2026-07-10\n";
		$csv .= "Bienvenue sur SwiftBoard !,Le bouton 🌙 est en haut à droite dans le header !,Marie Dupont,5,Bob Moreau,2026-07-10\n";
		$csv .= "TITRE EXACT D'UN SUJET DEJA EXISTANT,\"Ce commentaire sera AJOUTE a la suite du sujet existant (rien n'est recree).\",sophie.martin,member,2,,2026-08-01\n";
	}

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	echo "\xEF\xBB\xBF"; // BOM UTF-8 pour Excel
	echo $csv; // phpcs:ignore — CSV output
}


// ============================================================================
// 8. LECTURE NATIVE .XLSX (v5.3.5 — EXI-IMPORT-01)
// Un .xlsx est un ZIP XML : xl/sharedStrings.xml + xl/worksheets/*.xml.
// Le client demandait : « est-ce que j'uploade le fichier EXCEL ? » —
// avant : refusé, export CSV obligatoire. Maintenant : lecture directe
// (première feuille du classeur), sans dépendance externe.
// ============================================================================

/**
 * Lit les lignes de la première feuille d'un classeur .xlsx.
 *
 * @param string                           $path Chemin du fichier .xlsx (tmp upload).
 * @param array<int, array<string, mixed>> $log Journal, modifié par référence.
 * @return array<int, array<int, string>>|false Lignes lues, ou false + log d'erreur.
 */
function swiftboard_import_xlsx_read_rows( $path, array &$log ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'msg'     => '⚠️ ZipArchive indisponible : exportez en CSV.',
			'warning' => true,
		);
		return false;
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'msg'   => '❌ Fichier .xlsx illisible (archive invalide)',
			'error' => true,
		);
		return false;
	}

	// Chaînes partagées (les textes des cellules y vivent).
	$shared = array();
	$sx     = $zip->getFromName( 'xl/sharedStrings.xml' );
	if ( $sx ) {
		$doc = @simplexml_load_string( $sx );
		if ( $doc ) {
			foreach ( $doc->si as $si ) {
				$txt = '';
				foreach ( $si->xpath( './/t' ) ?: array() as $t ) {
					$txt .= (string) $t;
				}
				$shared[] = $txt;
			}
		}
	}

	// Première feuille via workbook.xml (+ relations), repli sheet1.xml.
	$sheet_path = 'xl/worksheets/sheet1.xml';
	$wb         = $zip->getFromName( 'xl/workbook.xml' );
	$rel        = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
	if ( $wb && $rel ) {
		$wbDoc  = @simplexml_load_string( $wb );
		$relDoc = @simplexml_load_string( $rel );
		if ( $wbDoc && $relDoc ) {
			$rid = '';
			foreach ( $wbDoc->xpath( './/*[local-name()="sheet"]' ) ?: array() as $sh ) {
				$a   = $sh->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships', true );
				$rid = (string) ( $a->id ?? '' );
				break;
			}
			foreach ( $relDoc->Relationship as $r ) {
				if ( (string) $r['Id'] === $rid ) {
					$sheet_path = 'xl/' . ltrim( preg_replace( '#^/?xl/#', '', (string) $r['Target'] ), '/' );
					break;
				}
			}
		}
	}
	$sheet = $zip->getFromName( $sheet_path );
	$zip->close();
	if ( ! $sheet ) {
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'msg'   => '❌ Feuille de calcul introuvable dans le .xlsx',
			'error' => true,
		);
		return false;
	}
	$doc = @simplexml_load_string( $sheet );
	if ( ! $doc ) {
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'msg'   => '❌ XML de la feuille illisible',
			'error' => true,
		);
		return false;
	}

	$rows = array();
	foreach ( $doc->xpath( './/*[local-name()="sheetData"]/*[local-name()="row"]' ) ?: array() as $row ) {
		$cells = array();
		$max   = -1;
		foreach ( $row->xpath( './*[local-name()="c"]' ) ?: array() as $c ) {
			$ref = (string) ( $c['r'] ?? '' );
			if ( $ref ) {
				$letters = preg_replace( '/[0-9]+/', '', $ref );
				$col     = 0;
				foreach ( str_split( strtoupper( $letters ) ) as $ch ) {
					$col = $col * 26 + ( ord( $ch ) - 64 );
				}
				--$col;
			} else {
				$col = $max + 1;
			}
			$max  = max( $max, $col );
			$type = (string) ( $c['t'] ?? '' );
			$val  = '';
			if ( 's' === $type ) {
				$val = $shared[ (int) (string) $c->v ] ?? '';
			} elseif ( 'inlineStr' === $type ) {
				foreach ( $c->xpath( './/*[local-name()="t"]' ) ?: array() as $t ) {
					$val .= (string) $t;
				}
			} else {
				$val = (string) $c->v;
			}
			$cells[ $col ] = trim( $val );
		}
		if ( $max >= 0 ) {
			$line = array();
			for ( $i = 0; $i <= $max; $i++ ) {
				$line[] = $cells[ $i ] ?? '';
			}
			if ( implode( '', $line ) !== '' ) {
				$rows[] = $line;
			}
		}
	}
	return $rows;
}

/**
 * Sérialise des lignes de cellules en texte CSV (virgule + guillemets),
 * le format attendu par swiftboard_str_getcsv_all().
 *
 * @param array<int, array<int, string>> $rows Lignes.
 * @return string CSV.
 */
function swiftboard_import_rows_to_csv( array $rows ) {
	$out = '';
	foreach ( $rows as $line ) {
		$cells = array();
		foreach ( $line as $cell ) {
			$cell = (string) $cell;
			if ( strpbrk( $cell, ",\"\r\n" ) !== false ) {
				$cell = '"' . str_replace( '"', '""', $cell ) . '"';
			}
			$cells[] = $cell;
		}
		$out .= implode( ',', $cells ) . "\n";
	}
	return $out;
}
