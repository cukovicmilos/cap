-- Caritas Assistant Portal Database Setup

CREATE DATABASE IF NOT EXISTS cap_db;
USE cap_db;

-- Tabela korisnika (upravitelj, radnici, volonteri)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    ime_prezime VARCHAR(255) NOT NULL,
    datum_rodjenja DATE,
    adresa TEXT,
    grad VARCHAR(100),
    telefon VARCHAR(20),
    tip_korisnika ENUM('upravitelj', 'radnik', 'volonter') NOT NULL,
    strucna_sprema VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela usluga
CREATE TABLE usluge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naziv VARCHAR(255) NOT NULL,
    prosecno_vreme INT NOT NULL, -- u minutima
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela nivoa invaliditeta
CREATE TABLE nivoi_invaliditeta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naziv VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela učestalosti odlaska
CREATE TABLE ucestalost_odlaska (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naziv VARCHAR(255) NOT NULL,
    dani_nedelje JSON, -- čuva dane kao JSON array
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela štićenika
CREATE TABLE sticenike (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ime_prezime VARCHAR(255) NOT NULL,
    datum_rodjenja DATE NOT NULL,
    adresa TEXT NOT NULL,
    grad VARCHAR(100) NOT NULL,
    telefon VARCHAR(20),
    penzioner BOOLEAN DEFAULT FALSE,
    nivo_invaliditeta_id INT,
    ucestalost_odlaska_id INT,
    placa_participaciju BOOLEAN DEFAULT FALSE,
    kako_je_postao_korisnik TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (nivo_invaliditeta_id) REFERENCES nivoi_invaliditeta(id),
    FOREIGN KEY (ucestalost_odlaska_id) REFERENCES ucestalost_odlaska(id)
);

-- Tabela za povezivanje štićenika sa uslugama (many-to-many)
CREATE TABLE sticanik_usluge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sticanik_id INT NOT NULL,
    usluga_id INT NOT NULL,
    FOREIGN KEY (sticanik_id) REFERENCES sticenike(id) ON DELETE CASCADE,
    FOREIGN KEY (usluga_id) REFERENCES usluge(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sticanik_usluga (sticanik_id, usluga_id)
);

-- Tabela za dodeljivanje štićenika radnicima (many-to-many)
CREATE TABLE korisnik_sticenike (
    id INT AUTO_INCREMENT PRIMARY KEY,
    korisnik_id INT NOT NULL,
    sticanik_id INT NOT NULL,
    FOREIGN KEY (korisnik_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sticanik_id) REFERENCES sticenike(id) ON DELETE CASCADE,
    UNIQUE KEY unique_korisnik_sticanik (korisnik_id, sticanik_id)
);

-- Tabela poseta
CREATE TABLE posete (
    id INT AUTO_INCREMENT PRIMARY KEY,
    korisnik_id INT NOT NULL,
    sticanik_id INT NOT NULL,
    datum_posete DATE NOT NULL,
    vreme_pocetka TIME NOT NULL,
    vreme_kraja TIME,
    ukupno_vreme INT, -- u minutima
    status ENUM('zakazana', 'u_toku', 'zavrsena', 'otkazana') DEFAULT 'zakazana',
    napomene TEXT,
    sinhronizovano BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (korisnik_id) REFERENCES users(id),
    FOREIGN KEY (sticanik_id) REFERENCES sticenike(id)
);

-- Tabela za usluge koje su urađene tokom posete
CREATE TABLE poseta_usluge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poseta_id INT NOT NULL,
    usluga_id INT NOT NULL,
    FOREIGN KEY (poseta_id) REFERENCES posete(id) ON DELETE CASCADE,
    FOREIGN KEY (usluga_id) REFERENCES usluge(id) ON DELETE CASCADE
);

-- Dodavanje default upravitelja
INSERT INTO users (email, password, ime_prezime, tip_korisnika) 
VALUES ('milos@studiopresent.com', MD5('miki1818'), 'Miloš - Upravitelj', 'upravitelj');

-- Dodavanje osnovnih usluga
INSERT INTO usluge (naziv, prosecno_vreme) VALUES 
('Kupovina', 60),
('Pripremanje hrane', 45),
('Pomoć oko spremanja', 30),
('Medicinske usluge', 90);

-- Dodavanje osnovnih nivoa invaliditeta
INSERT INTO nivoi_invaliditeta (naziv) VALUES 
('I kategorija - 100% invaliditet'),
('II kategorija - 75% invaliditet'),
('III kategorija - 50% invaliditet'),
('IV kategorija - 25% invaliditet');

-- Dodavanje osnovnih učestalosti odlaska
INSERT INTO ucestalost_odlaska (naziv, dani_nedelje) VALUES 
('Dnevno', '[1,2,3,4,5,6,7]'),
('Svaki drugi dan', '[1,3,5,7]'),
('3 puta nedeljno', '[1,3,5]'),
('Jednom nedeljno', '[1]');