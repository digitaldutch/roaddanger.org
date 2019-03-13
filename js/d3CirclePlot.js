

function CrashPartnerGraph(divID, data, optionsUser=[], onClickPoint=null) {
  let widthContainer   = 600;
  let heightContainer  = 400;
  let margin           = {top: 40, right: 10, bottom: 10, left: 40};
  let xLabel           = '';
  let yLabel           = '';
  const iconWidth      = 20;

  if (optionsUser) {
    if (optionsUser.width       !== undefined) widthContainer  = optionsUser.width;
    if (optionsUser.height      !== undefined) heightContainer = optionsUser.height;
    if (optionsUser.xLabel      !== undefined) xLabel          = optionsUser.xLabel;
    if (optionsUser.yLabel      !== undefined) yLabel          = optionsUser.yLabel;
  }

  let widthGraph  = widthContainer  - margin.left - margin.right;
  let heightGraph = heightContainer - margin.top  - margin.bottom;

  // Clear current chart
  d3.select('#' + divID).selectAll('svg').remove();

  // Create new graph
  let svg = d3.select('#' + divID)
    .append('svg')
    .attr('id', 'svgGraph')
    // Make SVG responsive by preserving aspect ratio,  adding viewbox and omitting width and height attributes
    .attr("preserveAspectRatio", "xMinYMin meet")
    .attr("viewBox", `0 0 ${widthContainer} ${heightContainer}`)

  let layerGraph = svg.append('svg')
    .attr('x', margin.left)
    .attr('y', margin.top)
    .attr('width',  widthGraph)
    .attr('height', heightGraph)
    .append('g');

  let victimModes  = d3.map(data, d => d.victimMode).keys();
  let partnerModes = d3.map(data, d => d.partnerMode).keys();
  let valueExtent  = d3.extent(data.map(p => p.value));

  let xScale = d3.scaleBand()
    .domain(partnerModes)
    .range([margin.left, widthContainer - margin.right]);

  let yScale = d3.scaleBand()
    .domain(victimModes.reverse())
    .range([heightContainer - margin.bottom, margin.top]);

  // x-axis
  svg.append('g')
    .attr('class', 'x-axis')
    .style('font-size', 12)
    .attr('transform', `translate(0, ${margin.top})`)
    .call(d3.axisTop(xScale).tickSize(0))
    .select('.domain').remove();

  // y-axis
  svg.append('g')
    .attr('class', 'y-axis')
    .style('font-size', 12)
    .attr('transform', `translate(${margin.left}, 0)`)
    .call(d3.axisLeft(yScale).tickSize(0))
    .select('.domain').remove();

  // x-axis image ticks
  svg.select('.x-axis').selectAll('text').remove();
  svg.selectAll('.x-axis .tick').data(partnerModes)
    .append('image')
    .attr('xlink:href', d => '/images/' + getModeImage(parseInt(d)))
    .attr('x',          -10 + 'px')
    .attr('y',          (-iconWidth + 5) + 'px')
    .attr('width',      iconWidth)
    .attr('height',     iconWidth);

  // y-axis image ticks
  svg.select('.y-axis').selectAll('text').remove();
  svg.selectAll('.y-axis .tick').data(victimModes)
    .append('image')
    .attr('xlink:href', d => '/images/' + getModeImage(parseInt(d)))
    .attr('x', (-iconWidth + 5) + 'px')
    .attr('y', -10 + 'px')
    .attr('width', iconWidth)
    .attr('height',iconWidth);

  // Square root scale, because value = area πr2
  let rScale = d3.scaleSqrt()
    .domain(valueExtent)
    .range([1, 0.7 * yScale.bandwidth()]);

  if (xLabel) {
    svg.append('text')
      .attr('class', 'graphTitle')
      .attr('transform', `translate(${widthContainer / 2}, 30)`)
      .attr('dy', '-1em')
      .style('text-anchor', 'middle')
      .text(xLabel);
  }

  if (yLabel) {
    svg.append('text')
      .attr('transform', 'rotate(-90)')
      .attr('class', 'graphTitle')
      .attr('x', - heightContainer / 2)
      .attr('y', 0)
      .attr('dy', '1em')
      .style('text-anchor', 'middle')
      .text(yLabel);
  }

  // create a tooltip
  var tooltip = d3.select('#' + divID)
    .append('div')
    .attr('class',             'tooltip')
    .style('display',          'none')
    .style('position',         'absolute')
    .style('background-color', 'white')
    .style('font-size',        '12px')
    .style('border',           'solid 1px #666')
    .style('border-radius',    '5px')
    .style('padding',          '2px 5px');

  function getModeImage(value){
    return value === -1? 'unilateral.svg' : transportationImageFileName(value);
  }

  let mouseover = function(data) {
    tooltip.style('display', 'flex');
    d3.select(this)
      .style('cursor', data => data.value===0? 'default' : 'pointer')
      .style('stroke', 'black');
  };

  let mousemove = function(data) {
    const pointMouse = d3.mouse(this);
    const svgWidth = d3.select('#' + divID).node().getBoundingClientRect().width;
    const scaleFactor = svgWidth / widthContainer;
    pointMouse[0] = pointMouse[0] * scaleFactor;
    pointMouse[1] = pointMouse[1] * scaleFactor;

    const modeText = data.partnerMode === -1? 'Eenzijdig ongeluk' : 'Tegenpartij: ' + transportationModeText(data.partnerMode);
    let html = modeText + '<br>';
    html += `${data.value} ${transportationModeText(data.victimMode)}`;
    html += data.value === 1? ' dode' : ' doden';
    tooltip.html(html)
      .style('left', (pointMouse[0] + 10) + 'px')
      .style('top',  (pointMouse[1] - 30) + 'px');
  };

  let mouseleave = function(data) {
    tooltip
      .style('display', 'none');

    d3.select(this)
      .style('cursor', 'default')
      .style('stroke', 'none');
  };

  let pointClick = function(data){
    if (onClickPoint && (data.value > 0)) onClickPoint(data);
  };

  // add the circles
  svg.selectAll()
    .data(data)
    .enter()
    .append('circle')
      .attr('cx',       d => xScale(d.partnerMode) + xScale.bandwidth()/2)
      .attr('cy',       d => yScale(d.victimMode)  + yScale.bandwidth()/2)
      .attr('r',        0)
      .style('fill',    d => d.value > 0? '#df3b34' : '#999999')
      .style('opacity', '0.8')
    .on('mouseover',  mouseover)
    .on('mousemove',  mousemove)
    .on('mouseleave', mouseleave)
    .on('click',      pointClick)
    .transition()
      .ease(d3.easeSin)
      .duration(2000)
      .attr('r',        d => d.value > 0? rScale(d.value) : 1);

}
