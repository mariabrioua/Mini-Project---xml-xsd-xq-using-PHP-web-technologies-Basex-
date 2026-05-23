(: requetes.xq :)

(:  Q1  :)
(: Liste complète des membres :)
(: Affiche pour chaque membre : identifiant, nom complet, email et libellé de sa catégorie :)

(: Charger le document XML :)
let $doc := doc("club.xml")

return

<membres>
{
(: Parcourir tous les éléments membre :)
for $m in $doc//membre

(: Récupérer la catégorie correspondante via la référence categorieRef :)
let $cat :=
$doc//categorie[@id = $m/@categorieRef]

return

<membre id="{$m/@id}">
  (: Nom complet : prénom suivi du nom :)
  <nomComplet>
    {string($m/prenom)} {string($m/nom)}
  </nomComplet>

  (: Adresse email du membre :)
  <email>
    {string($m/email)}
  </email>

  (: Libellé de la catégorie (pas juste l'ID) :)
  <categorie>
    {string($cat/@libelle)}
  </categorie>
</membre>

}
</membres>

,

(:  Q2  :)
(: Liste des concours organisés, triés par date croissante :)
(: Affiche pour chaque concours : titre, date, coefficient et libellé de la catégorie :)

(: Charger le document XML :)
let $doc := doc("club.xml")

return

<concours>
{
(: Parcourir tous les éléments concours :)
for $c in $doc//concours/concours

(: Récupérer la catégorie liée au concours :)
let $cat :=
$doc//categorie[@id = $c/@categorieRef]

(: Trier par date croissante :)
order by $c/@date

return

<concours>
  (: Titre du concours :)
  <titre>{string($c/titre)}</titre>

  (: Date du concours au format ISO :)
  <date>{string($c/@date)}</date>

  (: Coefficient multiplicateur :)
  <coefficient>
    {string($c/@coefficient)}
  </coefficient>

  (: Libellé de la catégorie concernée :)
  <categorie>
    {string($cat/@libelle)}
  </categorie>
</concours>

}
</concours>

,

(:  Q3  :)
(: Calcul des scores de chaque participant par concours :)
(: Formule : score = (complexite + tempsExecution) × coefficient :)

(: Charger le document XML :)
let $doc := doc("club.xml")

return

<resultats>
{
(: Parcourir chaque concours :)
for $c in $doc//concours/concours

return

<concours titre="{$c/titre}">
{
(: Parcourir chaque participant du concours :)
for $p in $c//participant

(: Récupérer le membre correspondant via membreRef :)
let $m :=
$doc//membre[@id = $p/@membreRef]

(: Calculer le score selon la formule imposée :)
let $score :=
(xs:integer($p/complexite)
+
xs:integer($p/tempsExecution))
*
xs:decimal($c/@coefficient)

return

<participant>

  (: Nom complet du participant :)
  <nom>
    {string($m/prenom)} {string($m/nom)}
  </nom>

  (: Score de complexité algorithmique :)
  <complexite>
    {string($p/complexite)}
  </complexite>

  (: Temps d'exécution en millisecondes :)
  <tempsExecution>
    {string($p/tempsExecution)}
  </tempsExecution>

  (: Score final arrondi à 2 décimales :)
  <score>
    {format-number($score,"0.00")}
  </score>

</participant>

}
</concours>

}
</resultats>

,

(:  Q4  :)
(: Vainqueur de chaque concours (score maximum) :)
(: En cas d'égalité, tous les ex-aequo sont affichés :)

(: Charger le document XML :)
let $doc := doc("club.xml")

return

<vainqueurs>
{
(: Parcourir chaque concours :)
for $c in $doc//concours/concours

(: Calculer tous les scores pour trouver le maximum :)
let $scores :=

for $p in $c//participant

return

(xs:integer($p/complexite)
+
xs:integer($p/tempsExecution))
*
xs:decimal($c/@coefficient)

(: Identifier le score maximum :)
let $maxScore := max($scores)

return

<concours titre="{$c/titre}">
{

(: Parcourir à nouveau les participants pour trouver le(s) vainqueur(s) :)
for $p in $c//participant

(: Récupérer les informations du membre :)
let $m :=
$doc//membre[@id = $p/@membreRef]

(: Recalculer le score de ce participant :)
let $score :=
(xs:integer($p/complexite)
+
xs:integer($p/tempsExecution))
*
xs:decimal($c/@coefficient)

(: Filtrer : ne garder que le(s) participant(s) avec le score max :)
where $score = $maxScore

return

<vainqueur>

  (: Nom complet du vainqueur :)
  <nom>
    {string($m/prenom)} {string($m/nom)}
  </nom>

  (: Score obtenu :)
  <score>
    {format-number($score,"0.00")}
  </score>

</vainqueur>

}
</concours>

}
</vainqueurs>

,

(:  Q5  :)
(: Membres d'une catégorie donnée, triés alphabétiquement :)
(: Paramétré avec une variable $categorie contenant le libellé :)

(: Charger le document XML :)
let $doc := doc("club.xml")

(: Variable paramétrable : libellé de la catégorie recherchée :)
let $categorie :=
"Intelligence Artificielle"

return

<membres categorie="{$categorie}">
{

(: Parcourir tous les membres :)
for $m in $doc//membre

(: Récupérer la catégorie du membre :)
let $cat :=
$doc//categorie[@id = $m/@categorieRef]

(: Filtrer : ne garder que les membres de la catégorie choisie :)
where string($cat/@libelle)
= $categorie

(: Trier par nom puis par prénom :)
order by $m/nom,
         $m/prenom

return

<membre>

  (: Nom complet du membre :)
  <nomComplet>
    {string($m/prenom)}
    {string($m/nom)}
  </nomComplet>

  (: Adresse email :)
  <email>
    {string($m/email)}
  </email>

</membre>

}
</membres>