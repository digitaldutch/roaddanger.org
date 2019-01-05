<?php

require_once '../initialize.php';

global $VERSION;

$ourEmail = DOMAIN_EMAIL;

$mainHTML = <<<HTML
<div id="main" class="pageInner bgWhite">
  
<div class="pageSubTitle">Over deze site</div>
  
<div class="sectionTitle">Wat is het doel van deze site?</div>  

<p>Hetongeluk.nl is in eerste plaats een database. 
Doel van deze site is meer inzicht te krijgen in hoe Nederlandse media schrijven over verkeersongelukken. 
Iedereen kan helpen om ongevallenberichtgeving te verzamelen: 
maak daarvoor een account aan via het profiel menu rechtsboven.
</p>
  
<div class="sectionTitle">Hoe voeg ik een bericht aan deze database toe?</div>

<p>
<ul>
<li>Registreer jezelf via het icoontje rechtsboven.</li> 
<li>Kopieer een link/url van een bericht over een ongeluk op een nieuws website.</li>
<li>Log in of registreer en klik op het plus icoontje rechtsboven om een nieuw ongeluk toe te voegen</li>
<li>Plak de url in het bovenste veld "Artikel link (URL)" en klik op "Artikel ophalen".</li> 
<li>Vul de datum van het ongeluk in (dit is niet per se de datum van het bericht), vul het aantal doden en gewonden in en de betrokken voertuigen en eventuele andere kenmerken (zoals betrokkenheid van kinderen en dieren).</li> 
<li>Meerdere berichten over één en hetzelfde ongeluk plaatsen we bij elkaar. Om een bericht bij een al in de database opgenomen ongeluk toe te voegen klik je op de drie bolletjes naast de icoontjes bij het ongeluk en kies je: Artikel toevoegen.</li>
<li>Klik op Opslaan.</li> 
</ul>
</p> 

<div class="sectionTitle">Wie telt als "gewond"?</div>
<p>
We tellen iemand als gewond als die als "gewond" wordt benoemd in een bericht of als een verkeersdeelnemer is afgevoerd met een ambulance / naar het ziekenhuis. We maken geen onderscheid tussen licht- en zwaargewonden. 
</p>

<div class="sectionTitle">Wat is "dood"?</div>
<p>
We tellen de doden zoals die benoemd worden in de berichten. 
</p>

<div class="sectionTitle">Kloppen de cijfers met de werkelijkheid?</div>
<p>
We streven geen volledigheid na en tellen enkel de gewonden en doden op zoals benoemd in de ongevallenberichten die we 
in deze database verzamelen. Dubbeltellingen van ongelukken halen we eruit, maar dat gebeurt niet altijd meteen. Het 
doel van deze site is niet volledigheid van cijfers, maar het krijgen van begrip over hoe we via media met elkaar 
praten over ongelukken. De cijfers geven een globaal beeld van wat er aan slachtoffers en verkeersdeelnemers 
in deze database te vinden is.
</p>

<div class="sectionTitle">Wie beheren deze site?</div>
<p>Hetongeluk.nl is begonnen als spin-off van het journalistieke project over verkeersongevallen van journalist 
Thalia Verkade en planoloog Marco te Brömmelstroet <a href="https://decorrespondent.nl/collectie/verkeersongevallen" target="_blank">op De Correspondent</a>. 
Beheerders zijn journalist Thalia Verkade en programmeur Jan Derk Stegeman. 
Vragen, opmerkingen kunnen naar <a href="mailto:$ourEmail">$ourEmail</a>.
</p>

<div class="sectionTitle">Ik wil helpen modereren/samenwerken/de data gebruiken/de code uitbouwen, kan dat?</div>
<p>
Fijn, stuur een mailtje naar <a href="mailto:$ourEmail">$ourEmail</a>. Alle code van de deze website is open source en beschikbaar op <a href="https://github.com/janderk2/hetongeluk" target="_blank">github</a>.</p>
  
<div class="sectionTitle">Icoontjes</div>
<p>Icoontjes zijn van 
<a href="https://www.flaticon.com" target="icons">flaticon</a>, 
<a href="https://www.freepik.com" target="icons">freepik</a>,
<a href="https://www.flaticon.com/authors/mavadee" target="icons">mavadee</a>,
<a href="https://www.flaticon.com/authors/monkik" target="icons">monkik</a>,
<a href="https://www.onlinewebfonts.com">Online Web Fonts</a>,
<a href="https://www.flaticon.com/authors/retinaicons" target="icons">retinaicons</a> en
<a href="https://www.flaticon.com/authors/smalllikeart" target="icons">smalllikeart</a>.
</p>   
  
</div>
HTML;

$html =
  getHTMLBeginMain('Over deze site', '', 'initPageUser') .
  $mainHTML .
  getHTMLEnd();

echo $html;