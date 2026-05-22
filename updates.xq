(: updates.xq :)
(: Fichier des mises à jour XQuery Update Facility :)

(:  UPDATE 1  :)
(: Insertion d'un nouveau membre dans la catégorie Développement Web :)
(: Le membre M013 est ajouté avec un identifiant unique au format Mxxx :)
(: Il est rattaché à la catégorie C2 (Développement Web) via categorieRef :)

insert node

<membre id="M013"
         categorieRef="C2">

  <nom>Zerrouk</nom>

  <prenom>Lyna</prenom>

  <email>l.zerrouk@club.dz</email>

</membre>

into doc("club.xml")//membres

,

(:  UPDATE 2  :)
(: Modification du coefficient du concours CO2 :)
(: Le coefficient passe de 1.2 à 2.0 pour augmenter le poids de ce concours :)
(: Avant : coefficient="1.2" — Après : coefficient="2.0" :)

replace value of node

doc("club.xml")//concours[@id="CO2"]/@coefficient

with "2.0"

,

(:  UPDATE 3  :)
(: Suppression du participant M001 du concours CO1 :)
(: Le concours CO1 subsiste avec les autres participants (M005, M007) :)

delete node

doc("club.xml")//concours[@id="CO1"]
//participant[@membreRef="M001"]