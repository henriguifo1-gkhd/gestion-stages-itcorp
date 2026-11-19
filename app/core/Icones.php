<?php
/**
 * Icônes SVG intégrées (jeu « Lucide », tracé simple 24x24) : aucune dépendance externe
 * (pas de CDN, l'application fonctionne hors ligne sur le réseau interne).
 *
 * Utilisation : <?= icone('briefcase') ?>  ou  <?= icone('clock', 'ic-lg') ?>
 */

function icone(string $nom, string $classe = ''): string
{
    static $chemins = [
        'dashboard'   => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'candidature' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><circle cx="12" cy="13" r="2"/><path d="M8.5 19c.5-1.7 1.8-2.5 3.5-2.5s3 .8 3.5 2.5"/>',
        'stagiaire'   => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.1 2.7 3 6 3s6-1.9 6-3v-5"/>',
        'convention'  => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="m9 15 2 2 4-4"/>',
        'stage'       => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/>',
        'livrable'    => '<path d="m21 8-9-5-9 5 9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'evaluation'  => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>',
        'attestation' => '<circle cx="12" cy="8" r="5"/><path d="M8.5 12.5 7 21l5-2.5L17 21l-1.5-8.5"/>',
        'utilisateurs'=> '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'        => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'bell'        => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'key'         => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.3-9.3"/><path d="m16 7 3 3"/><path d="m14 9 2 2"/>',
        'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'chevron-down'=> '<path d="m6 9 6 6 6-6"/>',
        'chevrons-left'=> '<path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/>',
        'menu'        => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'search'      => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'clock'       => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'check-circle'=> '<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="m9 11 3 3L22 4"/>',
        'loader'      => '<path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/>',
        'plus'        => '<path d="M5 12h14M12 5v14"/>',
        'eye'         => '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/>',
        'pencil'      => '<path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
        'trash'       => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'download'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
        'x'           => '<path d="M18 6 6 18M6 6l12 12"/>',
        'alert'       => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'alert-circle'=> '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'check'       => '<path d="M20 6 9 17l-5-5"/>',
        'info'        => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'history'     => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
        'calendar'    => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'building'    => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>',
        'file-text'   => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/>',
        'link'        => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'mail'        => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'lock'        => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'phone'       => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/>',
        'arrow-left'  => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    ];
    $contenu = $chemins[$nom] ?? $chemins['info'];
    $cls = trim('ic ' . $classe);
    return '<svg class="' . htmlspecialchars($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $contenu . '</svg>';
}

/** Logo losange IT CORP (version claire pour fond blanc, ou « sur fond foncé »). */
function logo_itcorp(string $classe = '', bool $surFond = false): string
{
    $trait = $surFond ? '#ffffff' : '#00AEEF';
    $texte = '#ffffff';
    return '<svg class="' . htmlspecialchars($classe) . '" viewBox="0 0 100 100" aria-hidden="true">'
        . '<polygon points="50,3 97,50 50,97 3,50" fill="none" stroke="' . $trait . '" stroke-opacity="0.95" stroke-width="4"/>'
        . '<polygon points="50,20 80,50 50,80 20,50" fill="#00AEEF"/>'
        . '<text x="50" y="54" font-family="\'Times New Roman\', Times, serif" font-size="13" font-weight="600" letter-spacing="0.3" fill="' . $texte . '" text-anchor="middle">IT CORP</text>'
        . '</svg>';
}

/** Libellé lisible d'un statut (même table que badge() côté navigateur). */
function libelle_statut(string $statut): string
{
    static $labels = [
        'en_attente' => 'En attente', 'validee' => 'Validée', 'rejetee' => 'Rejetée', 'terminee' => 'Terminée',
        'acceptee' => 'Acceptée', 'refusee' => 'Refusée', 'desistee' => 'Désistée', // statut de LA candidature
        'actif' => 'Actif', 'desiste' => 'Désisté', 'archive' => 'Archivé',
        'a_venir' => 'À venir', 'en_cours' => 'En cours', 'termine' => 'Terminé', 'interrompu' => 'Interrompu',
        'valide' => 'Validé', 'rejete' => 'Rejeté',
    ];
    return $labels[$statut] ?? $statut;
}
