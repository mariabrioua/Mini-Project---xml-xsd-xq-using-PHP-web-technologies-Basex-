let $doc := doc("club.xml")
return
<rapport>
  <!-- Q1 - Liste complète des membres avec leurs catégories -->
  <membres>
  {
    for $membre in $doc//membre
    let $cat := $doc//categorie[@id = $membre/@categorieRef]
    return
      <membre id="{data($membre/@id)}">
        <nomComplet>{concat($membre/prenom, " ", $membre/nom)}</nomComplet>
        <email>{data($membre/email)}</email>
        <categorie>{data($cat/@libelle)}</categorie>
      </membre>
  }
  </membres>

  <!-- Q2 - Liste des concours organisés triés par date croissante -->
  <concours>
  {
    for $c in $doc//concours/concours
    let $cat := $doc//categorie[@id = $c/@categorieRef]
    order by xs:date($c/@date)
    return
      <concours id="{data($c/@id)}">
        <titre>{data($c/titre)}</titre>
        <date>{data($c/@date)}</date>
        <coefficient>{data($c/@coefficient)}</coefficient>
        <categorie>{data($cat/@libelle)}</categorie>
      </concours>
  }
  </concours>

  <!-- Q3 - Calcul du score de chaque participant -->
  <resultats>
  {
    for $c in $doc//concours/concours
    return
      <concours id="{data($c/@id)}">
        <titre>{data($c/titre)}</titre>
        {
          for $p in $c/participants/participant
          let $m := $doc//membre[@id = $p/@membreRef]
          let $complexite := xs:decimal($p/complexite)
          let $temps := xs:decimal($p/tempsExecution)
          let $coef := xs:decimal($c/@coefficient)
          let $score := ($complexite + $temps) * $coef
          return
            <participant id="{data($p/@membreRef)}">
              <nomComplet>{concat($m/prenom, " ", $m/nom)}</nomComplet>
              <complexite>{$complexite}</complexite>
              <tempsExecution>{$temps}</tempsExecution>
              <score>{format-number($score, "0.00")}</score>
            </participant>
        }
      </concours>
  }
  </resultats>

  <!-- Q4 - Vainqueur de chaque concours -->
  <vainqueurs>
  {
    for $c in $doc//concours/concours
    let $coef := xs:decimal($c/@coefficient)
    let $scores :=
      for $p in $c/participants/participant
      return (xs:decimal($p/complexite) + xs:decimal($p/tempsExecution)) * $coef
    let $maxScore := max($scores)
    return
      <concours id="{data($c/@id)}">
        <titre>{data($c/titre)}</titre>
        {
          for $p in $c/participants/participant
          let $m := $doc//membre[@id = $p/@membreRef]
          let $score := (xs:decimal($p/complexite) + xs:decimal($p/tempsExecution)) * $coef
          where $score = $maxScore
          return
            <vainqueur id="{data($p/@membreRef)}">
              <nom>{data($m/nom)}</nom>
              <prenom>{data($m/prenom)}</prenom>
              <score>{format-number($score, "0.00")}</score>
            </vainqueur>
        }
      </concours>
  }
  </vainqueurs>

  <!-- Q5 - Membres d'une catégorie donnée, triés par nom puis prénom -->
  {
    let $categorie := "Intelligence Artificielle"
    let $cat := $doc//categorie[@libelle = $categorie]
    return
    <membres-categorie libelle="{$categorie}">
    {
      for $m in $doc//membre[@categorieRef = $cat/@id]
      order by data($m/nom), data($m/prenom)
      return
        <membre id="{data($m/@id)}">
          <nom>{data($m/nom)}</nom>
          <prenom>{data($m/prenom)}</prenom>
          <email>{data($m/email)}</email>
        </membre>
    }
    </membres-categorie>
  }
</rapport>