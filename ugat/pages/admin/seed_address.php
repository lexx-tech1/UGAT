<?php
// ============================================================
//  seed_address.php — UGAT TrainTrack
//  Run this ONCE to create + seed address tables.
//  Place this file in the same folder as your other PHP files
//  (e.g. next to get_address.php / save_workshops.php).
//
//  Visit: http://localhost/UGAT/your-admin-folder/seed_address.php
//  DELETE this file after running successfully.
// ============================================================

require_once '../../config/db.php';  // Adjust path if needed — same as get_address.php

$log = [];

// ── Helper ────────────────────────────────────────────────────
function run(mysqli $conn, string $sql, string $label, array &$log): void {
    if ($conn->query($sql)) {
        $log[] = "✅ $label";
    } else {
        $log[] = "❌ $label — {$conn->error}";
    }
}

// ── 1. CREATE TABLES ──────────────────────────────────────────
run($conn, "
CREATE TABLE IF NOT EXISTS regions (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    psgc_code VARCHAR(20)  DEFAULT NULL,
    name      VARCHAR(120) NOT NULL,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
", "Create regions table", $log);

run($conn, "
CREATE TABLE IF NOT EXISTS provinces (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    psgc_code VARCHAR(20)  DEFAULT NULL,
    name      VARCHAR(120) NOT NULL,
    region_id INT UNSIGNED NOT NULL,
    INDEX idx_region (region_id),
    INDEX idx_name   (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
", "Create provinces table", $log);

run($conn, "
CREATE TABLE IF NOT EXISTS cities (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    psgc_code   VARCHAR(20)  DEFAULT NULL,
    name        VARCHAR(120) NOT NULL,
    province_id INT UNSIGNED NOT NULL,
    INDEX idx_province (province_id),
    INDEX idx_name     (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
", "Create cities table", $log);

run($conn, "
CREATE TABLE IF NOT EXISTS barangays (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    psgc_code VARCHAR(20)  DEFAULT NULL,
    name      VARCHAR(120) NOT NULL,
    city_id   INT UNSIGNED NOT NULL,
    INDEX idx_city (city_id),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
", "Create barangays table", $log);

// ── 2. CHECK IF ALREADY SEEDED ────────────────────────────────
$countResult = $conn->query("SELECT COUNT(*) AS cnt FROM regions");
$count = $countResult ? (int)$countResult->fetch_assoc()['cnt'] : 0;

if ($count > 0) {
    $log[] = "ℹ️ Regions already seeded ($count rows). Skipping data insert.";
    $log[] = "   Delete this file — it is no longer needed.";
    printLog($log);
    exit;
}

// ── 3. SEED DATA (from ph_address.js) ────────────────────────
// Region → Province → City/Municipality
// Barangays are NOT included here — import PSGC SQL for those.

$PH_DATA = [
    "NCR - National Capital Region" => [
        "Metro Manila" => ["Caloocan","Las Piñas","Makati","Malabon","Mandaluyong","Manila","Marikina","Muntinlupa","Navotas","Parañaque","Pasay","Pasig","Pateros","Quezon City","San Juan","Taguig","Valenzuela"]
    ],
    "Region I - Ilocos Region" => [
        "Ilocos Norte" => ["Adams","Bacarra","Badoc","Bangui","Banna","Batac City","Burgos","Carasi","Currimao","Dingras","Dumalneg","Laoag City","Marcos","Nueva Era","Pagudpud","Paoay","Pasuquin","Piddig","Pinili","San Nicolas","Sarrat","Solsona","Vintar"],
        "Ilocos Sur"   => ["Alilem","Banayoyo","Bantay","Burgos","Cabugao","Candon City","Caoayan","Cervantes","Galimuyod","Gregorio del Pilar","Lidlidda","Magsingal","Mankayan","Nagbukel","Narvacan","Quirino","Salcedo","San Emilio","San Esteban","San Ildefonso","San Juan","San Vicente","Santa","Santa Catalina","Santa Cruz","Santa Lucia","Santa Maria","Santiago","Santo Domingo","Sigay","Sinait","Sugpon","Suyo","Tagudin","Vigan City"],
        "La Union"     => ["Agoo","Aringay","Bacnotan","Bagulin","Balaoan","Bangar","Bauang","Burgos","Caba","Luna","Naguilian","Pugo","Rosario","San Fernando City","San Gabriel","San Juan","Santo Tomas","Santol","Sudipen","Tubao"],
        "Pangasinan"   => ["Aguilar","Alaminos City","Alcala","Anda","Asingan","Balungao","Bani","Basista","Bautista","Bayambang","Binalonan","Binmaley","Bolinao","Bugallon","Burgos","Calasiao","Dagupan City","Dasol","Infanta","Labrador","Laoac","Lingayen","Mabini","Malasiqui","Manaoag","Mangaldan","Mangatarem","Mapandan","Natividad","Pozorrubio","Rosales","San Carlos City","San Fabian","San Jacinto","San Manuel","San Nicolas","San Quintin","Santa Barbara","Santa Maria","Santo Tomas","Sison","Sual","Tayug","Umingan","Urbiztondo","Urdaneta City","Villasis"]
    ],
    "Region II - Cagayan Valley" => [
        "Batanes"       => ["Basco","Itbayat","Ivana","Mahatao","Sabtang","Uyugan"],
        "Cagayan"       => ["Abulug","Alcala","Allacapan","Amulung","Aparri","Baggao","Ballesteros","Buguey","Calayan","Camalaniugan","Claveria","Enrile","Gattaran","Gonzaga","Iguig","Lal-lo","Lasam","Pamplona","Peñablanca","Piat","Rizal","Sanchez-Mira","Santa Ana","Santa Praxedes","Santa Teresita","Santo Niño","Solana","Tuao","Tuguegarao City"],
        "Isabela"       => ["Alicia","Angadanan","Aurora","Benito Soliven","Burgos","Cabagan","Cabatuan","Cauayan City","Cordon","Delfin Albano","Dinapigue","Divilacan","Echague","Gamu","Ilagan City","Jones","Luna","Maconacon","Mallig","Naguilian","Palanan","Quezon","Quirino","Ramon","Reina Mercedes","Roxas","San Agustin","San Guillermo","San Isidro","San Manuel","San Mariano","San Mateo","San Pablo","Santa Maria","Santiago City","Santo Tomas","Tumauini"],
        "Nueva Vizcaya" => ["Alfonso Castañeda","Ambaguio","Aritao","Bagabag","Bambang","Bayombong","Diadi","Dupax del Norte","Dupax del Sur","Kasibu","Kayapa","Quezon","Santa Fe","Solano","Villaverde"],
        "Quirino"       => ["Aglipay","Cabarroguis","Diffun","Maddela","Nagtipunan","Saguday"]
    ],
    "Region III - Central Luzon" => [
        "Aurora"      => ["Baler","Casiguran","Dilasag","Dinalungan","Dingalan","Dipaculao","Maria Aurora","San Luis"],
        "Bataan"      => ["Abucay","Bagac","Balanga City","Dinalupihan","Hermosa","Limay","Mariveles","Morong","Orani","Orion","Pilar","Samal"],
        "Bulacan"     => ["Angat","Balagtas","Baliuag","Bocaue","Bulakan","Bustos","Calumpit","Doña Remedios Trinidad","Guiguinto","Hagonoy","Malolos City","Marilao","Meycauayan City","Norzagaray","Obando","Pandi","Paombong","Plaridel","Pulilan","San Ildefonso","San Jose del Monte City","San Miguel","San Rafael","Santa Maria"],
        "Nueva Ecija" => ["Aliaga","Bongabon","Cabanatuan City","Cabiao","Carranglan","Cuyapo","Gabaldon","Gapan City","General Mamerto Natividad","General Tinio","Guimba","Jaen","Laur","Licab","Llanera","Lupao","Munoz City","Nampicuan","Palayan City","Pantabangan","Peñaranda","Quezon","Rizal","San Antonio","San Isidro","San Jose City","San Leonardo","Santa Rosa","Santo Domingo","Talavera","Talugtug","Zaragoza"],
        "Pampanga"    => ["Angeles City","Apalit","Arayat","Bacolor","Candaba","Floridablanca","Guagua","Lubao","Mabalacat City","Macabebe","Magalang","Masantol","Mexico","Minalin","Porac","San Fernando City","San Luis","San Simon","Santa Ana","Santa Rita","Santo Tomas","Sasmuan"],
        "Tarlac"      => ["Anao","Bamban","Camiling","Capas","Concepcion","Gerona","La Paz","Mayantoc","Moncada","Paniqui","Pura","Ramos","San Clemente","San Jose","San Manuel","Santa Ignacia","Tarlac City","Victoria"],
        "Zambales"    => ["Botolan","Cabangan","Candelaria","Castillejos","Iba","Masinloc","Olongapo City","Palauig","San Antonio","San Felipe","San Marcelino","San Narciso","Santa Cruz","Subic"]
    ],
    "Region IV-A - CALABARZON" => [
        "Batangas" => ["Agoncillo","Alitagtag","Balayan","Balete","Batangas City","Bauan","Calaca","Calatagan","Cuenca","Ibaan","Laurel","Lemery","Lian","Lipa City","Lobo","Mabini","Malvar","Mataas na Kahoy","Nasugbu","Padre Garcia","Rosario","San Jose","San Juan","San Luis","San Nicolas","San Pascual","Santa Teresita","Santo Tomas","Taal","Talisay","Tanauan City","Taysan","Tingloy","Tuy"],
        "Cavite"   => ["Alfonso","Amadeo","Bacoor City","Carmona","Cavite City","Dasmariñas City","General Emilio Aguinaldo","General Mariano Alvarez","General Trias City","Imus City","Indang","Kawit","Magallanes","Maragondon","Mendez","Naic","Noveleta","Rosario","Silang","Tagaytay City","Tanza","Ternate","Trece Martires City"],
        "Laguna"   => ["Alaminos","Bay","Biñan City","Cabuyao City","Calamba City","Calauan","Cavinti","Famy","Kalayaan","Liliw","Los Baños","Luisiana","Lumban","Mabitac","Magdalena","Majayjay","Nagcarlan","Paete","Pagsanjan","Pakil","Pangil","Pila","Rizal","San Pablo City","San Pedro City","Santa Cruz","Santa Maria","Santa Rosa City","Siniloan","Victoria"],
        "Quezon"   => ["Agdangan","Alabat","Atimonan","Buenavista","Burdeos","Calauag","Candelaria","Catanauan","Dolores","General Luna","General Nakar","Guinayangan","Gumaca","Infanta","Jomalig","Lopez","Lucban","Lucena City","Macalelon","Mauban","Mulanay","Padre Burgos","Pagbilao","Panukulan","Patnanungan","Perez","Pitogo","Plaridel","Polillo","Quezon","Real","Sampaloc","San Andres","San Antonio","San Francisco","San Narciso","Sariaya","Tagkawayan","Tayabas City","Tiaong","Unisan"],
        "Rizal"    => ["Angono","Antipolo City","Baras","Binangonan","Cainta","Cardona","Jalajala","Morong","Pililla","Rodriguez","San Mateo","Tanay","Taytay","Teresa"]
    ],
    "Region IV-B - MIMAROPA" => [
        "Marinduque"        => ["Boac","Buenavista","Gasan","Mogpog","Santa Cruz","Torrijos"],
        "Occidental Mindoro"=> ["Abra de Ilog","Calintaan","Looc","Lubang","Magsaysay","Mamburao","Paluan","Rizal","Sablayan","San Jose","Santa Cruz"],
        "Oriental Mindoro"  => ["Baco","Bansud","Bongabong","Bulalacao","Calapan City","Gloria","Mansalay","Naujan","Pinamalayan","Pola","Puerto Galera","Roxas","San Teodoro","Socorro","Victoria"],
        "Palawan"           => ["Aborlan","Agutaya","Araceli","Balabac","Bataraza","Brooke's Point","Busuanga","Cagayancillo","Coron","Culion","Cuyo","Dumaran","El Nido","Española","Kalayaan","Linapacan","Magsaysay","Narra","Puerto Princesa City","Quezon","Rizal","Roxas","San Vicente","Sofronio Española","Taytay"],
        "Romblon"           => ["Alcantara","Banton","Cajidiocan","Calatrava","Concepcion","Corcuera","Ferrol","Looc","Magdiwang","Odiongan","Romblon","San Agustin","San Andres","San Fernando","San Jose","Santa Fe","Santa Maria"]
    ],
    "Region V - Bicol Region" => [
        "Albay"            => ["Bacacay","Camalig","Daraga","Guinobatan","Jovellar","Legazpi City","Libon","Ligao City","Malilipot","Malinao","Manito","Oas","Pio Duran","Polangui","Rapu-Rapu","Santo Domingo","Tabaco City","Tiwi"],
        "Camarines Norte"  => ["Basud","Capalonga","Daet","Jose Panganiban","Labo","Mercedes","Paracale","San Lorenzo Ruiz","San Vicente","Santa Elena","Talisay","Vinzons"],
        "Camarines Sur"    => ["Baao","Balatan","Bato","Bombon","Buhi","Bula","Cabusao","Calabanga","Camaligan","Canaman","Caramoan","Del Gallego","Gainza","Garchitorena","Goa","Iriga City","Lagonoy","Libmanan","Lupi","Magarao","Milaor","Minalabac","Nabua","Naga City","Ocampo","Pamplona","Pasacao","Pili","Presentacion","Ragay","Sagñay","San Fernando","San Jose","Sipocot","Siruma","Tigaon","Tinambac"],
        "Catanduanes"      => ["Bagamanoc","Baras","Bato","Caramoran","Gigmoto","Pandan","Panganiban","San Andres","San Miguel","Viga","Virac"],
        "Masbate"          => ["Aroroy","Baleno","Balud","Batuan","Cataingan","Cawayan","Claveria","Dimasalang","Esperanza","Mandaon","Masbate City","Milagros","Mobo","Monreal","Palanas","Pio V. Corpuz","Placer","San Fernando","San Jacinto","San Pascual","Uson"],
        "Sorsogon"         => ["Barcelona","Bulan","Bulusan","Casiguran","Castilla","Donsol","Gubat","Irosin","Juban","Magallanes","Matnog","Pilar","Prieto Diaz","Santa Magdalena","Sorsogon City"]
    ],
    "Region VI - Western Visayas" => [
        "Aklan"             => ["Altavas","Balete","Banga","Batan","Buruanga","Ibajay","Kalibo","Lezo","Libacao","Madalag","Makato","Malay","Malinao","Nabas","New Washington","Numancia","Tangalan"],
        "Antique"           => ["Anini-y","Barbaza","Belison","Bugasong","Caluya","Culasi","Hamtic","Laua-an","Libertad","Pandan","Patnongon","San Jose","San Remigio","Sebaste","Sibalom","Tibiao","Tobias Fornier","Valderrama"],
        "Capiz"             => ["Cuartero","Dao","Dumalag","Dumarao","Ivisan","Jamindan","Ma-ayon","Mambusao","Panay","Panitian","Pilar","Pontevedra","President Roxas","Roxas City","Sapi-an","Sigma","Tapaz"],
        "Guimaras"          => ["Buenavista","Jordan","Nueva Valencia","San Lorenzo","Sibunag"],
        "Iloilo"            => ["Ajuy","Alimodian","Anilao","Badiangan","Balasan","Banate","Barotac Nuevo","Barotac Viejo","Batad","Bingawan","Cabatuan","Calinog","Carles","Concepcion","Dingle","Dueñas","Dumangas","Estancia","Guimbal","Igbaras","Iloilo City","Janiuay","Lambunao","Leganes","Lemery","Leon","Maasin","Miagao","Mina","New Lucena","Oton","Passi City","Pavia","Pototan","San Dionisio","San Enrique","San Joaquin","San Miguel","San Rafael","Santa Barbara","Sara","Tigbauan","Tubungan","Zarraga"],
        "Negros Occidental" => ["Bacolod City","Bago City","Binalbagan","Calatrava","Candoni","Cauayan","Enrique B. Magalona","Escalante City","Himamaylan City","Hinigaran","Hinoba-an","Ilog","Isabela","Kabankalan City","La Carlota City","La Castellana","Manapla","Moises Padilla","Murcia","Pontevedra","Pulupandan","Sagay City","San Carlos City","San Enrique","Silay City","Sipalay City","Talisay City","Toboso","Valladolid","Victorias City"]
    ],
    "Region VII - Central Visayas" => [
        "Bohol"           => ["Alburquerque","Alicia","Anda","Antequera","Baclayon","Balilihan","Batuan","Bien Unido","Bilar","Buenavista","Calape","Candijay","Carmen","Catigbian","Clarin","Corella","Cortes","Dagohoy","Danao","Dauis","Dimiao","Duero","Garcia Hernandez","Getafe","Guindulman","Inabanga","Jagna","Jetafe","Lila","Loay","Loboc","Loon","Mabini","Maribojoc","Panglao","Pilar","Pres. Carlos P. Garcia","Sagbayan","San Isidro","San Miguel","Sevilla","Sierra Bullones","Sikatuna","Tagbilaran City","Talibon","Trinidad","Tubigon","Ubay","Valencia"],
        "Cebu"            => ["Alcantara","Alcoy","Alegria","Aloguinsan","Argao","Asturias","Badian","Balamban","Bantayan","Barili","Bogo City","Boljoon","Borbon","Carcar City","Carmen","Catmon","Cebu City","Compostela","Consolacion","Cordova","Daanbantayan","Dalaguete","Danao City","Dumanjug","Ginatilan","Lapu-Lapu City","Liloan","Madridejos","Malabuyoc","Mandaue City","Medellin","Minglanilla","Moalboal","Naga City","Oslob","Pilar","Pinamungajan","Poro","Ronda","Samboan","San Fernando","San Francisco","San Remigio","Santa Fe","Santander","Sibonga","Sogod","Tabogon","Tabuelan","Talisay City","Toledo City","Tuburan","Tudela"],
        "Negros Oriental" => ["Ayungon","Bacong","Bais City","Basay","Bayawan City","Bindoy","Canlaon City","Dauin","Dumaguete City","Guihulngan City","Jimalalud","La Libertad","Mabinay","Manjuyod","Pamplona","San Jose","Santa Catalina","Siaton","Sibulan","Tanjay City","Tayasan","Valencia","Vallehermoso","Zamboanguita"],
        "Siquijor"        => ["Enrique Villanueva","Larena","Lazi","Maria","San Juan","Siquijor"]
    ],
    "Region VIII - Eastern Visayas" => [
        "Biliran"        => ["Almeria","Biliran","Cabucgayan","Caibiran","Culaba","Kawayan","Maripipi","Naval"],
        "Eastern Samar"  => ["Arteche","Balangiga","Balangkayan","Borongan City","Can-avid","Dolores","General MacArthur","Giporlos","Guiuan","Hernani","Jipapad","Lawaan","Llorente","Maslog","Maydolong","Mercedes","Oras","Quinapondan","Salcedo","San Julian","San Policarpo","Sulat","Taft"],
        "Leyte"          => ["Abuyog","Alangalang","Albuera","Babatngon","Barugo","Bato","Baybay City","Burauen","Calubian","Capoocan","Carigara","Dagami","Dulag","Hilongos","Hindang","Inopacan","Isabel","Jaro","Javier","Julita","Kananga","La Paz","Leyte","Liloan","Macarthur","Mahaplag","Matag-ob","Matalom","Mayorga","Merida","Ormoc City","Palo","Palompon","Pastrana","San Isidro","San Miguel","Santa Fe","Tabango","Tabontabon","Tacloban City","Tanauan","Tolosa","Tunga","Villaba"],
        "Northern Samar" => ["Allen","Biri","Bobon","Capul","Catarman","Catubig","Gamay","Laoang","Lapinig","Las Navas","Lavezares","Lope de Vega","Mapanas","Mondragon","Palapag","Pambujan","Rosario","San Antonio","San Isidro","San Jose","San Vicente","Silvino Lobos","Victoria"],
        "Samar"          => ["Almagro","Basey","Calbayog City","Calbiga","Catbalogan City","Daram","Gandara","Hinabangan","Jiabong","Marabut","Matuguinao","Motiong","Paranas","Pinabacdao","San Jorge","San Jose de Buan","San Sebastian","Santa Margarita","Santa Rita","Santo Niño","Tagapul-an","Talalora","Tarangnan","Villareal","Zumarraga"],
        "Southern Leyte" => ["Anahawan","Bontoc","Hinunangan","Hinundayan","Libagon","Liloan","Limasawa","Maasin City","Macrohon","Padre Burgos","Pintuyan","Saint Bernard","San Francisco","San Juan","San Ricardo","Silago","Sogod","Tomas Oppus"]
    ],
    "Region IX - Zamboanga Peninsula" => [
        "Zamboanga del Norte"  => ["Baliguian","Dapitan City","Dipolog City","Godod","Gutalac","Jose Dalman","Kalawit","Katipunan","La Libertad","Labason","Leon B. Postigo","Liloy","Manukan","Mutia","Piñan","Polanco","President Manuel A. Roxas","Rizal","Salug","San Miguel","Sergio Osmeña Sr.","Siayan","Sibuco","Sibutad","Sindangan","Siocon","Sirawai","Tampilisan"],
        "Zamboanga del Sur"    => ["Aurora","Bayog","Dimataling","Dinas","Dumalinao","Dumingag","Guipos","Josefina","Kumalarang","Labangan","Lapuyan","Mahayag","Margosatubig","Midsalip","Molave","Pagadian City","Pitogo","Ramon Magsaysay","San Miguel","San Pablo","Tabina","Tambulig","Tigbao","Tukuran","Vincenzo A. Sagun","Zamboanga City"],
        "Zamboanga Sibugay"    => ["Alicia","Buug","Diplahan","Imelda","Ipil","Kabasalan","Mabuhay","Malangas","Naga","Olutanga","Payao","Roseller Lim","Siay","Talusan","Titay","Tungawan"]
    ],
    "Region X - Northern Mindanao" => [
        "Bukidnon"          => ["Baungon","Cabanglasan","Damulog","Dangcagan","Don Carlos","Impasug-ong","Kadingilan","Kalilangan","Kibawe","Kitaotao","Lantapan","Libona","Malitbog","Manolo Fortich","Maramag","Pangantucan","Quezon","San Fernando","Sumilao","Talakag","Valencia City"],
        "Camiguin"          => ["Catarman","Guinsiliban","Mahinog","Mambajao","Sagay"],
        "Lanao del Norte"   => ["Bacolod","Baloi","Baroy","Iligan City","Kapatagan","Kauswagan","Kolambugan","Lala","Linamon","Magsaysay","Maigo","Munai","Nunungan","Pantao Ragat","Pantar","Poona Piagapo","Salvador","Sapad","Sultan Naga Dimaporo","Tagoloan","Tangcal","Tubod"],
        "Misamis Occidental"=> ["Aloran","Baliangao","Bonifacio","Calamba","Clarin","Concepcion","Don Victoriano Chiongbian","Jimenez","Lopez Jaena","Oroquieta City","Ozamiz City","Panaon","Plaridel","Sapang Dalaga","Sinacaban","Tangub City","Tudela"],
        "Misamis Oriental"  => ["Alubijid","Balingasag","Balingoan","Binuangan","Cagayan de Oro City","Claveria","El Salvador City","Gingoog City","Gitagum","Initao","Jasaan","Kinoguitan","Lagonglong","Laguindingan","Libertad","Lugait","Magsaysay","Manticao","Medina","Naawan","Opol","Salay","Sugbongcogon","Tagoloan","Talisayan","Villanueva"]
    ],
    "Region XI - Davao Region" => [
        "Davao de Oro"     => ["Compostela","Laak","Mabini","Maco","Maragusan","Mawab","Monkayo","Montevista","Nabunturan","New Bataan","Pantukan"],
        "Davao del Norte"  => ["Asuncion","Braulio E. Dujali","Carmen","Kapalong","New Corella","Panabo City","Samal City","San Isidro","Santo Tomas","Tagum City","Talaingod"],
        "Davao del Sur"    => ["Bansalan","Davao City","Digos City","Hagonoy","Kiblawan","Magsaysay","Malalag","Matanao","Padada","Santa Cruz","Sulop"],
        "Davao Occidental" => ["Don Marcelino","Jose Abad Santos","Malita","Santa Maria","Sarangani"],
        "Davao Oriental"   => ["Baganga","Banaybanay","Boston","Caraga","Cateel","Governor Generoso","Lupon","Manay","Mati City","San Isidro","Tarragona"]
    ],
    "Region XII - SOCCSKSARGEN" => [
        "Cotabato"       => ["Alamada","Allah Valley","Aleosan","Antipas","Arakan","Banisilan","Carmen","Kabacan","Kidapawan City","Libungan","Magpet","Makilala","Matalam","Midsayap","M'lang","Pigkawayan","Pikit","President Roxas","Tulunan"],
        "Sarangani"      => ["Alabel","Glan","Kiamba","Maasim","Maitum","Malapatan","Malungon"],
        "South Cotabato" => ["Banga","General Santos City","Koronadal City","Lake Sebu","Norala","Polomolok","Santo Niño","Surallah","T'boli","Tampakan","Tantangan","Tupi"],
        "Sultan Kudarat" => ["Bagumbayan","Columbio","Esperanza","Isulan","Kalamansig","Lambayong","Lebak","Lutayan","Palimbang","President Quirino","Senator Ninoy Aquino","Tacurong City"]
    ],
    "Region XIII - Caraga" => [
        "Agusan del Norte"  => ["Buenavista","Butuan City","Cabadbaran City","Carmen","Jabonga","Kitcharao","Las Nieves","Magallanes","Nasipit","Remedios T. Romualdez","Santiago","Tubay"],
        "Agusan del Sur"    => ["Bayugan City","Bunawan","Esperanza","La Paz","Loreto","Prosperidad","Rosario","San Francisco","San Luis","Santa Josefa","Santo Tomas","Sibagat","Talacogon","Trento","Veruela"],
        "Dinagat Islands"   => ["Basilisa","Cagdianao","Dinagat","Libjo","Loreto","San Jose","Tubajon"],
        "Surigao del Norte" => ["Alegria","Bacuag","Burgos","Claver","Dapa","Del Carmen","General Luna","Gigaquit","Mainit","Malimono","Pilar","Placer","San Benito","San Francisco","San Isidro","Santa Monica","Sison","Socorro","Surigao City","Tagana-an","Tubod"],
        "Surigao del Sur"   => ["Barobo","Bayabas","Bislig City","Cagwait","Cantilan","Carmen","Carrascal","Cortes","Hinatuan","Lanuza","Lianga","Lingig","Madrid","Marihatag","San Agustin","San Miguel","Tagbina","Tago","Tandag City"]
    ],
    "CAR - Cordillera Administrative Region" => [
        "Abra"             => ["Bangued","Boliney","Bucay","Bucloc","Daguioman","Danglas","Dolores","La Paz","Lacub","Lagangilang","Lagayan","Langiden","Licuan-Baay","Luba","Malibcong","Manabo","Peñarrubia","Pidigan","Pilar","Sallapadan","San Isidro","San Juan","San Quintin","Tayum","Tineg","Tubo","Villaviciosa"],
        "Apayao"           => ["Calanasan","Conner","Flora","Kabugao","Luna","Pudtol","Santa Marcela"],
        "Benguet"          => ["Atok","Baguio City","Bakun","Bokod","Buguias","Itogon","Kabayan","Kapangan","Kibungan","La Trinidad","Mankayan","Sablan","Tuba","Tublay"],
        "Ifugao"           => ["Aguinaldo","Alfonso Lista","Asipulo","Banaue","Hingyon","Hungduan","Kiangan","Lagawe","Lamut","Mayoyao","Tinoc"],
        "Kalinga"          => ["Balbalan","Lubuagan","Pasil","Pinukpuk","Rizal","Tabuk City","Tanudan","Tinglayan"],
        "Mountain Province"=> ["Barlig","Bauko","Besao","Bontoc","Natonin","Paracelis","Sabangan","Sadanga","Sagada","Tadian"]
    ],
    "BARMM - Bangsamoro Autonomous Region in Muslim Mindanao" => [
        "Basilan"     => ["Akbar","Al-Barka","Hadji Mohammad Ajul","Hadji Muhtamad","Isabela City","Lamitan City","Lantawan","Maluso","Sumisip","Tabuan-Lasa","Tipo-Tipo","Tuburan","Ungkaya Pukan"],
        "Lanao del Sur"=> ["Amai Manabilang","Bacolod-Kalawi","Balabagan","Balindong","Bayang","Binidayan","Buadiposo-Buntong","Bubong","Bumbaran","Butig","Calanogas","Ditsaan-Ramain","Ganassi","Kapai","Kapatagan","Lumba-Bayabao","Lumbaca-Unayan","Lumbatan","Lumbayanague","Madalum","Madamba","Maguing","Malabang","Marantao","Marawi City","Marogong","Masiu","Molundo","Mulondo","Pagayawan","Piagapo","Picong","Poona Bayabao","Pualas","Ranao","Saguiaran","Sultan Dumalondong","Sultan Gumander","Tagoloan II","Tamparan","Taraka","Tubaran","Tugaya","Wao"],
        "Maguindanao" => ["Ampatuan","Buldon","Buluan","Datu Abdullah Sangki","Datu Anggal Midtimbang","Datu Blah T. Sinsuat","Datu Hoffer Ampatuan","Datu Montawal","Datu Odin Sinsuat","Datu Paglas","Datu Piang","Datu Salibo","Datu Saudi-Ampatuan","Datu Unsay","General Salipada K. Pendatun","Guindulungan","Kabuntalan","Kotabato City","Mamasapano","Mangudadatu","Matanog","Northern Kabuntalan","Pagalungan","Paglat","Pandag","Parang","Rajah Buayan","Shariff Aguak","Shariff Saydona Mustapha","South Upi","Sultan Kudarat","Sultan Mastura","Sultan sa Barongis","Talayan","Talitay","Upi"],
        "Sulu"        => ["Hadji Panglima Tahil","Indanan","Jolo","Kalingalan Caluang","Lugus","Luuk","Maimbung","Old Panamao","Omar","Pandami","Panglima Estino","Pangutaran","Parang","Pata","Patikul","Siasi","Talipao","Tapul","Tongkil"],
        "Tawi-Tawi"   => ["Bongao","Languyan","Mapun","Panglima Sugala","Sapa-Sapa","Sibutu","Simunul","Sitangkai","South Ubian","Tandubas","Turtle Islands"]
    ],
];

// ── 4. INSERT WITH PREPARED STATEMENTS ───────────────────────
$conn->begin_transaction();

try {
    $stmtRegion   = $conn->prepare("INSERT INTO regions   (name) VALUES (?)");
    $stmtProvince = $conn->prepare("INSERT INTO provinces (name, region_id) VALUES (?, ?)");
    $stmtCity     = $conn->prepare("INSERT INTO cities    (name, province_id) VALUES (?, ?)");

    $regionCount   = 0;
    $provinceCount = 0;
    $cityCount     = 0;

    foreach ($PH_DATA as $regionName => $provinces) {
        $stmtRegion->bind_param("s", $regionName);
        $stmtRegion->execute();
        $regionId = (int)$conn->insert_id;
        $regionCount++;

        foreach ($provinces as $provinceName => $cities) {
            $stmtProvince->bind_param("si", $provinceName, $regionId);
            $stmtProvince->execute();
            $provinceId = (int)$conn->insert_id;
            $provinceCount++;

            foreach ($cities as $cityName) {
                $stmtCity->bind_param("si", $cityName, $provinceId);
                $stmtCity->execute();
                $cityCount++;
            }
        }
    }

    $stmtRegion->close();
    $stmtProvince->close();
    $stmtCity->close();

    $conn->commit();

    $log[] = "✅ Inserted $regionCount regions";
    $log[] = "✅ Inserted $provinceCount provinces";
    $log[] = "✅ Inserted $cityCount cities / municipalities";
    $log[] = "ℹ️ Barangays table created but empty — import PSGC SQL for barangay-level data";
    $log[] = "🎉 Done! <strong>Delete this file now.</strong>";

} catch (Throwable $e) {
    $conn->rollback();
    $log[] = "❌ Transaction failed: " . $e->getMessage();
}

printLog($log);

// ── Print helper ──────────────────────────────────────────────
function printLog(array $log): void {
    echo '<!DOCTYPE html><html><head>
    <meta charset="utf-8">
    <title>UGAT Address Seeder</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 600px; margin: 3rem auto; padding: 0 1rem; }
        h2   { color: #4B8423; }
        li   { padding: .3rem 0; font-size: .95rem; }
        .box { background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 1rem 1.5rem; }
    </style>
    </head><body>
    <h2>🌱 UGAT Address Seeder</h2>
    <div class="box"><ul>';
    foreach ($log as $line) {
        echo "<li>$line</li>";
    }
    echo '</ul></div></body></html>';
    exit;
}