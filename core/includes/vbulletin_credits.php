<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright 2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

// display the credits table for use in admin/mod control panels

print_form_header('index', 'home');
print_table_header($vbphrase['vbulletin_developers_and_contributors']);
print_column_style_code(array('white-space: nowrap', ''));

print_label_row('<b>' . $vbphrase['software_developed_by'] . '</b>', '
	vBulletin Solutions, Inc.,
	Internet Brands, Inc.
', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['project_management'] . '</b>', '
	Mark Jean,
	Gary Carroll
', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['development_lead'] . '</b>', '
	Kevin Sours
', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['development'] . '</b>', '
	Alan Ordu&ntilde;o,
	Aaron Bohac,
	Brett Morriss,
	Chen Xu,
	Danco Dimovski,
	David Grove,
	Edwin Brown,
	Fernando Varesi,
	Freddie Bingham,
	Glenn Vergara,
	Gregg Hartling,
	Jin-Soo Jo,
	Jorge Tiznado,
	Kyle Furlong,
	Michael Lavaveshkul,
	Paul Marsden,
	Reynaldo Hinojo,
	Tadeo Valencia,
	Xiaoyu Huang,
	Zoltan Szalay
', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['product_management_user_experience_visual_design'] . '</b>', '
	Neal Sainani,
	Alan Chiu,
	Olga Mandrosov,
	Joe Rosenblum,
	Fabian Schonholz,
	John McGanty
	', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['qa'] . '</b>', '
	Aileen Alba,
	Reenan Arbitrario,
	David Cwik,
	Chamberline Ekejiuba,
	Brett Johnson,
	Fei Leung,
	Allen H. Lin,
	Michael Mendoza,
	Miguel Monta&ntilde;o,
	Yves Rigaud,
	Meghan Sensenbach,
	Sean Tieu,
	Sebastiano Vassellatti,
	Andrew Vo,
	Alan Voong
', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['documentation'] . '</b>', '
	Trevor Hannant,
	Dick Johnson,
	Fei Leung,
	George Liu,
	Wayne Luke,
	Greg Overduin,
	Lynne Sands,
	Anna van Raaphorst,
	Zachery Woods
', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['bussines_operations_management_and_customer_support'] . '</b>', '
	John McGanty,
	Lawrence Cole,
	Christine Tran,
	Wayne Luke,
	Zachery Woods,
	Lynne Sands,
	Trevor Hannant,
	Joe Dibiasi,
	Michael Miller,
	Abdulla Ashoor,
	Yasser Hamed,
	Riasat Al Jamil,
	George Liu,
	Yves Rigaud,
	Dominic Schlatter,
	Aakif Nazir,
	Hartmut Voss,
	Mark Bowland,
	Ali Madkour,
	Rene Jimenez,
	Duane Piosca,
	Zuzanna Grande,
	Ariel Walker
', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['special_thanks_and_contributions'] . '</b>', '
	Abraham Miranda,
	Ace Shattock,
	Adam Bloch,
	Adrian Sacchi,
	Ahmed Al-Shobaty,
	Alexander Mueller,
	Allen Smith,
	Aman Singh,
	Anders Pettersson,
	Andew Simmons,
	Andrew Clarke,
	Anthony Falcone,
	Art Andrews Jr,
	Barry Chertov,
	Blake Bowden,
	Brad Amos,
	Brad Szopinski,
	Brandon Sheley,
	Brian Gunter,
	Campos Santos,
	Carl David Birch,
	Chad Billmyer,
	Chase Hausman,
	Chase Webb,
	Chris Dildy,
	Chris Riley,
	Chris Van Dyke,
	Christian Hoffmann,
	Christos Teriakis,
	Daniel Fatkic,
	David Gerard Hopwood,
	Domien Brandsma,
	Dominic Schlatter,
	Drew Pomerleau,
	Dylan Wheeler,
	Emon Khan,
	Eric Sizemore,
	Fillip Hannisdal,
	Gavin Clarke,
	Geoff Carew,
	George Boone,
	Hani Saad Alazmi,
	Hartmut Voss,
	Iain Kidd,
	Ivan Anfimov,
	Janusz Mocek,
	Jarvis Ka,
	Jaume L&oacute;pez,
	Jim Dudek,
	John Sandells,
	John Waltz,
	Jon Dickinson,
	Joseph DeTomaso,
	Juan Carlos Muriente,
	Kamal Saleh,
	Kareem Ashur,
	Kevin Hynes,
	Kevin Kivlehan,
	Kevin Wilkinson,
	Kira Lerner,
	Kostas Skiadas,
	Kym Farnik,
	Les Hill,
	Lionel Martelly,
	Lisa Swift,
	Marc Stridgen,
	Marco Mamdouh Fahem,
	Marcus Kielmann,
	Mark Bowland,
	Mark Hennyey,
	Mark Stroman,
	Matthew Sealey,
	Mattia Sparacino,
	Maurice De Stefano,
	Michael Biddle,
	Michael Matthews,
	Mike Fara,
	Mike Ford,
	Milad Kaleh,
	Miner,
	Neal Parry,
	Nicolas Boileau,
	Nuno Santos,
	Pam Ellars,
	Paul Holbrook,
	Pieter Verhaeghe,
	Rafael Reyes Jr,
	Ranga Basuru Thenuwara,
	Refael Iliaguyev,
	Rick Frerichs,
	Rob Collyer,
	Robert G Plank,
	Robert White,
	Ryan Smith,
	Sal Colascione,
	Steven Burke,
	Steven Lawrence,
	Sven Keller,
	Teascu Dorin,
	Ted Sendinski,
	Theodore Phillips,
	Todd A. Hoff,
	Vincent Scatigna,
	Vladimir Metelitsa,
	William Golighty,
	Zafer Bahadir
', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['copyright_enforcement_by'] . '</b>', '
	vBulletin Solutions, Inc.
', '', 'top', NULL, false);
print_table_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 41704 $
|| ####################################################################
\*======================================================================*/
?>
