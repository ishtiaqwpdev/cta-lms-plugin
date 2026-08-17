<?php
$root = dirname( __DIR__ );
$files = array(
	$root . '/assets/course-materials/lcsw-law-ethics/workbooks/CTA_LCSW_Law_and_Ethics_EP_WB1_Informed_Consent_Minors_and_Family_Involvement_Candidate_Edition_v1.0.docx',
	$root . '/assets/course-materials/lpcc-law-ethics/workbooks/CTA_LPCC_Law_and_Ethics_EP_WB01_Informed_Consent_Minors_and_Family_Involvement_Candidate_Edition_v1.0.docx',
);
foreach ( $files as $path ) {
	$z = new ZipArchive();
	$z->open( $path );
	$xml = $z->getFromName( 'word/document.xml' );
	$z->close();
	$dom = new DOMDocument();
	$dom->loadXML( $xml );
	$xp = new DOMXPath( $dom );
	$xp->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );
	echo basename( $path ) . ' paragraphs=' . $xp->query( '//w:p' )->length . ' tables=' . $xp->query( '//w:tbl' )->length . PHP_EOL;
}
