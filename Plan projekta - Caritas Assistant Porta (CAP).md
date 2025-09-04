
## Uvod

Caritas je međunarodna humanitarna organizacija katoličke crkve koja:

- Pruža humanitarnu pomoć ugroženim grupama
- Sprovodi socijalne programe i projekte
- Pomaže u vanrednim situacijama i prirodnim katastrofama
- Radi na smanjenju siromaštva i društvenom razvoju

Caritas deluje širom sveta kroz mrežu nacionalnih organizacija. Organizacija pomaže ljudima bez obzira na njihovu versku, nacionalnu ili etničku pripadnost.

---

## Caritas Assistant Porta (CAP)

Portal će biti korišten pre svega na teritoriji Subotice sa tendencijom širenja na drudge župe u rep. Srbiji.

Uloga portala je da pomogne u organizaciji i vođenju zaposlenih i volontera koji brinu o štićenicima - uglavnom starijim ljudima ili ljudima sa nekom poteškoćom.

Takođe, portal treba da ponudi pravilno izveštavanje sa detaljima svake asistencije, ukupnom vremenu provedenom kod štićenika i uslugom koja je pružena.

Ceo sistem se praktično sastoji iz dva dela:  

1. Portal za izveštavanje i administraciju > Koristi je upravitelj (folder: "cap_admin")
2. Mobile/Responsive PWA aplikacija > Koriste je zaposleni na terenu i volonteri (folder: "cap_pwa")

## 1. Portal za izveštavanje i administraciju (cap_admin)

CMS koji će služiti za administraciju:

## Vrste korisnika:

##### Upravitelj
Opis: Glavni administrator

Osnovna polja:
- Email
- Lozinka
- Ime i prezime  

Postavi default login za upravitelja:
Email: milos@studiopresent.com
Lozinka: miki1818

##### Radnici
Opis: Zaposleni (koji obilaze štićenike koji su na platnom spisku)

Osnovna polja:
- Email
- Lozinka
- Ime i prezime
- Datum rođenja
- Adresa
- Grad
- Mobilni telefon
- Stručna sprema (lista stručnih sprema iz Srbije)
- Dodeljeni štićenici (multi)

###### Volonteri
Opis: Radnici ali oni nisu na platnom spisku

- Email
- Lozinka
- Ime i prezime
- Datum rođenja
- Adresa
- Grad
- Telefon
- Dodeljeni štićenici (multi)

##### Štićenici:
Opis: Osobe koje obilaze radnici i volonteri 

- Ime i prezime
- Datum rođenja
- Adresa
- Grad
- Telefon
- Penzioner (DA/NE)
- Izbor usluga iz kategorija koje se mogu raditi kod štićenika - multiselect
	- Kupovina
	- Pripremanje hrane
	- Pomoć oko spremanja
	- Medicinske usluge
- Nivo invaliditeta (sa kratkim dodatnim opisom) - po zakonu R. Srbije
- Učestalost odlaska (dnevno, svaki drugi dan, tri puta nedeljeno, jednom nedeljeno)
- Plaća participaciju (DA/NE)
- Kako je postao korisnik (slobodan tekstualni unos)  


## Podešavanja

#### Administriranje usluga koje radnici pružaju štićenicima:
Polja:
- Naziv usluge
- Prosečno vreme trajanje jedne usluge

#### Administriranje nivoa inavliditeta:
Polja:
- Naziv: Nivo invaliditeta

#### Administriranje učestalosti odlaska kod štićenika:
Polja:
- Naziv (primer: "2 puta nedeljno")
- Selektor dana u nedelji po principu kalendarskog repeat widgeta (primer: selektovan utorak i petak)

### Izveštaji
Za upravitelja. Samo role upravitelj ima pristup administraciji (cap_admin)

- Po periodu, nedeljni, mesečni, godišnji
- Po štićeniku, po zaposlenom
- Po uslugama
- Eksport u Excel, CSV

## Posete
Upravitelj može da generiše posete uvek za naredne dve nedelje računajući od momenta kada se generisanje pokrenulo.

Mogu se generisati i individualne posete izborom štićenika i radnika, datum i vreme posete.

## 2. Mobilna aplikacija PWA (cap_pwa)

Ključna funkcija je local storage u sklopu PWA aplikacije koji treba da bude u sinhronizaciji sa cap_admin portalom onda kada je internet dostupan. Ovo je bitno jer ljudi koji su na terenu nekad nemaju data internet i tek mogu da sinhronizuju podatke kada dođu kući na lokalni wifi.

Mobilnu PWA aplikaciju će koristiti oni radnici koji idu na teren kod svojih štićenika.

Jedan radni dan radnika izgleda ovako:

1. Login u pwa app - remember me opcija u trajanju od 3 meseca
2. Zaposleni ili volonter vidi naloge kod kojih štićenika treba da ide danas, ali ima i kalendar da bi video ostale dane. Kalendar ima indikatore kojim danima radnik ima posetu kod štićenika
3. Kada stigne na lokaciju štićenika, radnik otvara aplikaciju, pronalazi dotičnog štićenika i radi “check in”. Pokreće timer.
4. Vreme počinje da se računa tj. pamtimo timestamp početka posete
5. Kada je posao završen, označavaju se usluge koje su urađene (nabavka, higijena…) Timer se zaustavlja.
6. Radi se “check out” i sada imamo vreme trajanja jedne posete koje mora da se pohrani u local storage tj. odmah u bazu ako je internet odmah dostupan.
7. Volonter odlazi kod drugog štićenika i posao kreće ispočetka.
8. Upravitelj vidi istoriju poseta u cap_admin

  
Kada telefon ima internet podaci se sinhronizuju u istom momentu.

Ukoliko internet nije dostupan, podaci se snimaju u local storage telefona - browsera.

Po ponovnoj dostupnosi interneta - i ako je PWA aplikacija otvorena, podaci će se automatski sinhronizovati na cap_admin i bazom.

Korisnik pwa aplikacije će dobiti vizuelnu indikaciju da su svi podaci sinhronizovani, tj. ako nema interneta - da podaci nisu sinhronizovani.

Ovaj proces se dešava automatski. Korisnik nema interakciju nad ovim postupkom.

---

## Tech. stack specifikacije

MAMP server sa MySQL se nalazi na http://127.0.0.1:8888/
Apache

**Backend:**
- PHP

**Baza:**
- MySQL
- Local storage

**Frontend:**
- Alpine.js za interaktivnost
- Tailwind za CSS
- Vanilla HTML

### Styling

**Fonts:**
- Naslovi: Poppins
- Body: Open Sans

**Colors:**
- Main color: #E24135
- Secondary color: #6E6767
- Background color: #6E6767

**Assets:**
- Logo svg: "global_assets/cap_logo.svg"
- Logo png: "global_assets/cap_logo.png"
- favicon: "global_assets/favicon.png"

**Icons:**
- Tailwind > Heroicons