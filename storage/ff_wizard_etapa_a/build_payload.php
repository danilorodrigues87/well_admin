<?php
/**
 * Gera mapa fileKey => yaml para Etapa A CollectionWizard e imprime JSON.
 */
$base = 'page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-';
$dir = __DIR__;

$files = [
    'page/id-Scaffold_4rp06q2q' => file_get_contents("$dir/page_meta.yaml"),
    'page/id-Scaffold_4rp06q2q/page-widget-tree-outline' => file_get_contents("$dir/tree_outline.yaml"),
];

foreach (glob("$dir/*.yaml") as $path) {
    $name = basename($path, '.yaml');
    if (in_array($name, ['page_meta', 'tree_outline'], true)) {
        continue;
    }
    $files[$base.$name] = file_get_contents($path);
}

// Step widgets embedded below as keyed content
$nodes = [];

$label = function (string $key, string $text): string {
    return <<<YAML
key: {$key}
type: Text
props:
  text:
    themeStyle: LABEL_MEDIUM
    selectable: false
    textValue:
      inputValue: {$text}
    colorValue:
      inputValue:
        themeColor: SECONDARY_TEXT
    heightValue:
      inputValue: 1.38
name: Text
YAML;
};

$title = function (string $key, string $text): string {
    return <<<YAML
key: {$key}
type: Text
props:
  text:
    themeStyle: TITLE_MEDIUM
    selectable: false
    textValue:
      inputValue: {$text}
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
    heightValue:
      inputValue: 1.35
name: Text
YAML;
};

$body = function (string $key, string $text, string $style = 'BODY_MEDIUM'): string {
    return <<<YAML
key: {$key}
type: Text
props:
  text:
    themeStyle: {$style}
    selectable: false
    textValue:
      inputValue: {$text}
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
name: Text
YAML;
};

$dropdown = function (string $key, string $name, array $labels, array $values, string $hint): string {
    $labYaml = '';
    foreach ($labels as $i => $lab) {
        $labYaml .= "        - translatableText:\n            textValue:\n              inputValue: {$lab}\n              mostRecentInputValue: {$lab}\n";
    }
    $valYaml = '';
    foreach ($values as $v) {
        $valYaml .= "        - serializedValue: \"{$v}\"\n";
    }
    return <<<YAML
key: {$key}
type: DropDown
props:
  padding: {}
  dropDown:
    dimensions:
      width:
        pixelsValue:
          inputValue: Infinity
      height:
        pixelsValue:
          inputValue: 48
    textStyle:
      themeStyle: BODY_MEDIUM
      colorValue:
        inputValue:
          themeColor: PRIMARY_TEXT
    optionsLabels:
      values:
{$labYaml}    optionsValues:
      values:
{$valYaml}    hintText:
      textValue:
        inputValue: {$hint}
        mostRecentInputValue: {$hint}
    valueType:
      dataType:
        scalarType: String
    fillColorValue:
      inputValue:
        themeColor: SECONDARY_BACKGROUND
      mostRecentInputValue:
        themeColor: SECONDARY_BACKGROUND
    borderRadiusValue:
      inputValue: 8
      mostRecentInputValue: 8
    borderColorValue:
      inputValue:
        themeColor: ALTERNATE
      mostRecentInputValue:
        themeColor: ALTERNATE
    borderWidthValue:
      inputValue: 1
      mostRecentInputValue: 1
    hidesUnderlineValue:
      inputValue: true
      mostRecentInputValue: true
    elevationValue:
      inputValue: 2
      mostRecentInputValue: 2
name: {$name}
YAML;
};

$textField = function (string $key, string $hint, int $maxLines = 1, string $kb = 'TEXT'): string {
    return <<<YAML
key: {$key}
type: TextField
props:
  padding: {}
  textField:
    textStyle:
      themeStyle: BODY_MEDIUM
      colorValue:
        inputValue:
          themeColor: PRIMARY_TEXT
    inputDecoration:
      inputBorderType: outline
      borderRadius:
        type: FF_BORDER_RADIUS_ALL
        allValue:
          inputValue: 8
      contentPadding:
        leftValue:
          inputValue: 16
        topValue:
          inputValue: 14
        rightValue:
          inputValue: 16
        bottomValue:
          inputValue: 14
      hintStyle:
        themeStyle: BODY_MEDIUM
        colorValue:
          inputValue:
            themeColor: SECONDARY_TEXT
      hintText:
        textValue:
          inputValue: {$hint}
          mostRecentInputValue: {$hint}
      borderColorValue:
        inputValue:
          themeColor: ALTERNATE
      filledValue:
        inputValue: true
      fillColorValue:
        inputValue:
          themeColor: SECONDARY_BACKGROUND
    keyboardType: {$kb}
    width:
      pixelsValue:
        inputValue: Infinity
    maxLinesValue:
      inputValue: {$maxLines}
name: TextField
YAML;
};

$button = function (string $key, string $text, string $fill = 'PRIMARY', string $textColor = 'PRIMARY_BACKGROUND'): string {
    return <<<YAML
key: {$key}
type: Button
props:
  button:
    text:
      themeStyle: TITLE_SMALL
      textValue:
        inputValue: {$text}
        mostRecentInputValue: {$text}
      colorValue:
        inputValue:
          themeColor: {$textColor}
      fontWeightValue:
        inputValue: w600
    borderRadius:
      type: FF_BORDER_RADIUS_ALL
      allValue:
        inputValue: 8
    dimensions:
      width:
        pixelsValue:
          inputValue: Infinity
      height:
        pixelsValue:
          inputValue: 48
    elevationValue:
      inputValue: 0
      mostRecentInputValue: 0
    fillColorValue:
      inputValue:
        themeColor: {$fill}
      mostRecentInputValue:
        themeColor: {$fill}
  padding: {}
name: Button
YAML;
};

$outlineBtn = function (string $key, string $text) use ($button): string {
    return <<<YAML
key: {$key}
type: Button
props:
  button:
    text:
      themeStyle: TITLE_SMALL
      textValue:
        inputValue: {$text}
        mostRecentInputValue: {$text}
      colorValue:
        inputValue:
          themeColor: PRIMARY
      fontWeightValue:
        inputValue: w600
    borderRadius:
      type: FF_BORDER_RADIUS_ALL
      allValue:
        inputValue: 8
    dimensions:
      width:
        pixelsValue:
          inputValue: Infinity
      height:
        pixelsValue:
          inputValue: 48
    elevationValue:
      inputValue: 0
      mostRecentInputValue: 0
    fillColorValue:
      inputValue:
        themeColor: SECONDARY_BACKGROUND
      mostRecentInputValue:
        themeColor: SECONDARY_BACKGROUND
    borderColorValue:
      inputValue:
        themeColor: ALTERNATE
      mostRecentInputValue:
        themeColor: ALTERNATE
    borderWidthValue:
      inputValue: 1
      mostRecentInputValue: 1
  padding: {}
name: Button
YAML;
};

$col = function (string $key, string $cross = 'cross_axis_start', int $spacing = 8): string {
    return <<<YAML
key: {$key}
type: Column
props:
  column:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: {$cross}
    scrollable: false
    listSpacing:
      spacingValue:
        inputValue: {$spacing}
    minSizeValue:
      inputValue: true
name: Column
YAML;
};

$row = function (string $key, int $spacing = 12): string {
    return <<<YAML
key: {$key}
type: Row
props:
  row:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: cross_axis_center
    listSpacing:
      spacingValue:
        inputValue: {$spacing}
  padding: {}
name: Row
YAML;
};

$icon = function (string $key, string $name, int $code): string {
    return <<<YAML
key: {$key}
type: Icon
props:
  icon:
    sizeValue:
      inputValue: 22
      mostRecentInputValue: 22
    colorValue:
      inputValue:
        themeColor: SECONDARY_TEXT
      mostRecentInputValue:
        themeColor: SECONDARY_TEXT
    iconDataValue:
      inputValue:
        codePoint: {$code}
        family: MaterialIcons
        matchTextDirection: false
        name: {$name}
  padding: {}
name: Icon
YAML;
};

$card = function (string $key): string {
    return <<<YAML
key: {$key}
type: Container
props:
  container:
    dimensions:
      width:
        percentOfContainingWidgetValue:
          inputValue: 100
    boxDecoration:
      colorValue:
        inputValue:
          themeColor: SECONDARY_BACKGROUND
      borderRadiusValue:
        inputValue: 12
      border:
        all:
          sizeValue:
            inputValue: 1
          colorValue:
            inputValue:
              themeColor: ALTERNATE
  padding:
    type: FF_PADDING_ALL
    allValue:
      inputValue: 12
name: Card
YAML;
};

// Step 2
$nodes['Column_cwst2root'] = $col('Column_cwst2root', 'cross_axis_stretch', 16);
$nodes['Text_cwst2ttl'] = $title('Text_cwst2ttl', '2. Coleta — Residuos');
$nodes['Column_cwst2add'] = $col('Column_cwst2add', 'cross_axis_stretch', 8);
$nodes['Text_cwst2trl'] = $label('Text_cwst2trl', 'Tipo de residuo');
$nodes['DropDown_cwst2tip'] = $dropdown('DropDown_cwst2tip', 'TipoResiduo', ['Residuo Infectante', 'Perfurocortante'], ['1', '2'], 'Buscar residuo');
$nodes['Row_cwst2qty'] = $row('Row_cwst2qty', 12);
$nodes['Column_cwst2qcol'] = $col('Column_cwst2qcol', 'cross_axis_start', 4);
$nodes['Text_cwst2qlb'] = $label('Text_cwst2qlb', 'Quantidade');
$nodes['TextField_cwst2qtd'] = $textField('TextField_cwst2qtd', '12,5', 1, 'NUMBER');
$nodes['Column_cwst2ucol'] = $col('Column_cwst2ucol', 'cross_axis_start', 4);
$nodes['Text_cwst2ulb'] = $label('Text_cwst2ulb', 'Unidade');
$nodes['DropDown_cwst2uni'] = $dropdown('DropDown_cwst2uni', 'Unidade', ['Kg', 'Litros', 'Unid.'], ['kg', 'l', 'un'], 'Unidade');
$nodes['Button_cwst2add'] = $button('Button_cwst2add', 'Adicionar', 'SUCCESS', 'PRIMARY_BACKGROUND');
$nodes['Text_cwst2lst'] = $label('Text_cwst2lst', 'Residuos adicionados');
$nodes['Container_cwst2r1'] = $card('Container_cwst2r1');
$nodes['Row_cwst2r1r'] = $row('Row_cwst2r1r', 8);
$nodes['Column_cwst2r1c'] = $col('Column_cwst2r1c', 'cross_axis_start', 2);
$nodes['Text_cwst2r1n'] = $body('Text_cwst2r1n', 'Residuo Infectante');
$nodes['Text_cwst2r1d'] = $body('Text_cwst2r1d', 'Classe A · 12,5 kg', 'LABEL_SMALL');
$nodes['Icon_cwst2r1x'] = $icon('Icon_cwst2r1x', 'delete_outline_rounded', 57703);
$nodes['Container_cwst2r2'] = $card('Container_cwst2r2');
$nodes['Row_cwst2r2r'] = $row('Row_cwst2r2r', 8);
$nodes['Column_cwst2r2c'] = $col('Column_cwst2r2c', 'cross_axis_start', 2);
$nodes['Text_cwst2r2n'] = $body('Text_cwst2r2n', 'Perfurocortante');
$nodes['Text_cwst2r2d'] = $body('Text_cwst2r2d', 'Classe E · 2 un', 'LABEL_SMALL');
$nodes['Icon_cwst2r2x'] = $icon('Icon_cwst2r2x', 'delete_outline_rounded', 57703);
$nodes['Text_cwst2sig'] = $label('Text_cwst2sig', 'Assinatura do cliente (opcional)');
$nodes['Container_cwst2pad'] = <<<YAML
key: Container_cwst2pad
type: Container
props:
  container:
    dimensions:
      width:
        percentOfContainingWidgetValue:
          inputValue: 100
      height:
        pixelsValue:
          inputValue: 140
    boxDecoration:
      colorValue:
        inputValue:
          themeColor: PRIMARY_BACKGROUND
      borderRadiusValue:
        inputValue: 8
      border:
        all:
          sizeValue:
            inputValue: 1
          colorValue:
            inputValue:
              themeColor: ALTERNATE
  padding:
    type: FF_PADDING_ALL
    allValue:
      inputValue: 16
name: AssinaturaPad
YAML;
$nodes['Text_cwst2ph'] = $body('Text_cwst2ph', 'Area para assinar', 'BODY_SMALL');
$nodes['Row_cwst2sigb'] = $row('Row_cwst2sigb', 12);
$nodes['Button_cwst2clr'] = $outlineBtn('Button_cwst2clr', 'Limpar');
$nodes['Button_cwst2sav'] = $button('Button_cwst2sav', 'Salvar assinatura');

// Step 3
$nodes['Column_cwst3root'] = $col('Column_cwst3root', 'cross_axis_stretch', 16);
$nodes['Text_cwst3ttl'] = $title('Text_cwst3ttl', '3. Relatorio / Evidencias');
$nodes['Text_cwst3rlb'] = $label('Text_cwst3rlb', 'Relatorio / observacoes');
$nodes['TextField_cwst3rel'] = $textField('TextField_cwst3rel', 'Descreva a coleta...', 4, 'MULTILINE');
$nodes['Text_cwst3elb'] = $label('Text_cwst3elb', 'Evidencias (ate 3 fotos)');
$nodes['Row_cwst3phs'] = $row('Row_cwst3phs', 8);
$photoSlot = function (string $sfx, string $label) use ($col, $icon, $body): array {
    $c = "Container_cwst3{$sfx}";
    $colK = "Column_cwst3{$sfx}c";
    $i = "Icon_cwst3{$sfx}i";
    $t = "Text_cwst3{$sfx}t";
    return [
        $c => <<<YAML
key: {$c}
type: Container
props:
  container:
    dimensions:
      width:
        pixelsValue:
          inputValue: 100
      height:
        pixelsValue:
          inputValue: 100
    boxDecoration:
      colorValue:
        inputValue:
          themeColor: SECONDARY_BACKGROUND
      borderRadiusValue:
        inputValue: 12
      border:
        all:
          sizeValue:
            inputValue: 1
          colorValue:
            inputValue:
              themeColor: ALTERNATE
  padding:
    type: FF_PADDING_ALL
    allValue:
      inputValue: 8
  expanded:
    expandedType: EXPANDED
    flexValue:
      inputValue: 1
name: PhotoSlot
YAML,
        $colK => $col($colK, 'cross_axis_center', 4),
        $i => $icon($i, 'add_a_photo_rounded', 58286),
        $t => $body($t, $label, 'LABEL_SMALL'),
    ];
};
foreach ($photoSlot('p1', 'Foto 1') as $k => $v) { $nodes[$k] = $v; }
foreach ($photoSlot('p2', 'Foto 2') as $k => $v) { $nodes[$k] = $v; }
foreach ($photoSlot('p3', 'Foto 3') as $k => $v) { $nodes[$k] = $v; }
$nodes['Button_cwst3sav'] = $button('Button_cwst3sav', 'Salvar relatorio');
$nodes['Container_cwst3ok'] = <<<YAML
key: Container_cwst3ok
type: Container
props:
  container:
    dimensions:
      width:
        percentOfContainingWidgetValue:
          inputValue: 100
    boxDecoration:
      colorValue:
        inputValue:
          themeColor: SUCCESS
      borderRadiusValue:
        inputValue: 12
  padding:
    type: FF_PADDING_ALL
    allValue:
      inputValue: 12
  opacity:
    opacityValue:
      inputValue: 0.15
      mostRecentInputValue: 0.15
name: ResumoOk
YAML;
// opacity 0.15 on whole container might make text hard to read - use alternate border success style instead
$nodes['Container_cwst3ok'] = <<<YAML
key: Container_cwst3ok
type: Container
props:
  container:
    dimensions:
      width:
        percentOfContainingWidgetValue:
          inputValue: 100
    boxDecoration:
      colorValue:
        inputValue:
          themeColor: SECONDARY_BACKGROUND
      borderRadiusValue:
        inputValue: 12
      border:
        all:
          sizeValue:
            inputValue: 1
          colorValue:
            inputValue:
              themeColor: SUCCESS
  padding:
    type: FF_PADDING_ALL
    allValue:
      inputValue: 12
name: ResumoOk
YAML;
$nodes['Column_cwst3okc'] = $col('Column_cwst3okc', 'cross_axis_start', 4);
$nodes['Text_cwst3ok1'] = $body('Text_cwst3ok1', 'Relatorio salvo — confira antes de concluir');
$nodes['Text_cwst3ok2'] = $body('Text_cwst3ok2', 'Residuos: 2 · Motorista: Joao · Fotos: 1', 'LABEL_SMALL');

// Step 4
$nodes['Column_cwst4root'] = $col('Column_cwst4root', 'cross_axis_stretch', 12);
$nodes['Text_cwst4ttl'] = $title('Text_cwst4ttl', '4. Destinador');
$nodes['Text_cwst4dlb'] = $label('Text_cwst4dlb', 'Destinador final');
$nodes['DropDown_cwst4des'] = $dropdown('DropDown_cwst4des', 'Destinador', ['Destinador Padrao'], ['1'], 'Selecione');
$nodes['Text_cwst4tlb'] = $label('Text_cwst4tlb', 'Tratamento / destinacao');
$nodes['DropDown_cwst4tra'] = $dropdown('DropDown_cwst4tra', 'Tratamento', ['Autoclave', 'Incineracao'], ['Autoclave', 'Incineracao'], 'Selecione');
$nodes['Text_cwst4olb'] = $label('Text_cwst4olb', 'Obs. destinador');
$nodes['TextField_cwst4obs'] = $textField('TextField_cwst4obs', 'Observacoes');
$nodes['Row_cwst4rec'] = $row('Row_cwst4rec', 8);
$nodes['Switch_cwst4sw'] = <<<YAML
key: Switch_cwst4sw
type: Switch
props:
  platformType: MATERIAL
  switchWidget:
    disabled:
      disabledValue:
        inputValue: false
    initialValueValue:
      inputValue: false
      mostRecentInputValue: false
    activeTrackColorValue:
      inputValue:
        themeColor: PRIMARY
    inactiveThumbColorValue:
      inputValue:
        themeColor: SECONDARY_TEXT
    inactiveTrackColorValue:
      inputValue:
        themeColor: ALTERNATE
name: RecebidoSwitch
YAML;
$nodes['Text_cwst4rcl'] = $body('Text_cwst4rcl', 'Recebido no destinador');
$nodes['Text_cwst4elb'] = $label('Text_cwst4elb', 'Data de encerramento');
$nodes['Container_cwst4dat'] = <<<YAML
key: Container_cwst4dat
type: Container
props:
  container:
    dimensions:
      width:
        percentOfContainingWidgetValue:
          inputValue: 100
      height:
        pixelsValue:
          inputValue: 48
    boxDecoration:
      colorValue:
        inputValue:
          themeColor: SECONDARY_BACKGROUND
      borderRadiusValue:
        inputValue: 8
      border:
        all:
          sizeValue:
            inputValue: 1
          colorValue:
            inputValue:
              themeColor: ALTERNATE
  padding:
    type: FF_PADDING_ONLY
    leftValue:
      inputValue: 16
    rightValue:
      inputValue: 16
name: DataEncerramento
YAML;
$nodes['Row_cwst4drow'] = $row('Row_cwst4drow', 8);
$nodes['Text_cwst4dval'] = $body('Text_cwst4dval', '06/10/2026');
$nodes['Icon_cwst4dico'] = $icon('Icon_cwst4dico', 'calendar_today_rounded', 983309);
$nodes['Text_cwst4help'] = $body('Text_cwst4help', 'O MTR SINIR e gerado depois na listagem de Coletas.', 'LABEL_SMALL');
$nodes['Button_cwst4sav'] = $outlineBtn('Button_cwst4sav', 'Salvar destinador');
$nodes['Button_cwst4fin'] = $button('Button_cwst4fin', 'Concluir relatorio', 'SUCCESS', 'PRIMARY_BACKGROUND');
$nodes['Button_cwst4can'] = <<<YAML
key: Button_cwst4can
type: Button
props:
  button:
    text:
      themeStyle: TITLE_SMALL
      textValue:
        inputValue: Cancelar rascunho
        mostRecentInputValue: Cancelar rascunho
      colorValue:
        inputValue:
          themeColor: ERROR
      fontWeightValue:
        inputValue: w600
    borderRadius:
      type: FF_BORDER_RADIUS_ALL
      allValue:
        inputValue: 8
    dimensions:
      width:
        pixelsValue:
          inputValue: Infinity
      height:
        pixelsValue:
          inputValue: 48
    elevationValue:
      inputValue: 0
      mostRecentInputValue: 0
    fillColorValue:
      inputValue:
        themeColor: SECONDARY_BACKGROUND
      mostRecentInputValue:
        themeColor: SECONDARY_BACKGROUND
    borderColorValue:
      inputValue:
        themeColor: ERROR
      mostRecentInputValue:
        themeColor: ERROR
    borderWidthValue:
      inputValue: 1
      mostRecentInputValue: 1
  padding: {}
name: CancelarButton
YAML;

foreach ($nodes as $key => $yaml) {
    $files[$base.$key] = $yaml;
}

// Header bind to page state
$files[$base.'Text_t4amqzk8'] = <<<YAML
key: Text_t4amqzk8
type: Text
props:
  text:
    themeStyle: TITLE_MEDIUM
    selectable: false
    textValue:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: clienteNome
              key: cwcnom
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: Scaffold_4rp06q2q
      mostRecentInputValue: Padaria Sao Jose
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
  responsiveVisibility: {}
parameterValues: {}
valueKey: {}
YAML;

$files[$base.'Text_6hcel9pv'] = <<<YAML
key: Text_6hcel9pv
type: Text
props:
  text:
    themeStyle: LABEL_SMALL
    selectable: false
    textValue:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: clienteEndereco
              key: cwcend
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: Scaffold_4rp06q2q
      mostRecentInputValue: "Rua das Flores, 120 - Centro"
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
    heightValue:
      inputValue: 1.27
name: Text
YAML;

$out = $dir.'/push_payload.json';
file_put_contents($out, json_encode(['projectId' => 'well-coletas-by2777', 'fileKeyToContent' => $files], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
echo 'files='.count($files)."\n";
echo 'out='.$out."\n";
