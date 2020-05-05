

function CrashPartnerGraph(divID, data, optionsUser=[], filter=null) {
  let widthContainer   = 700;
  let heightContainer  = 500;
  let margin           = {top: 50, left: 50, right: 10, bottom: 10};
  let xLabel           = '';
  let yLabel           = '';
  const iconWidth      = 30;

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

  let colorScale = d3.scaleLinear()
    .domain(valueExtent)
    .range(["#f5977b", "#ff0000"]);

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
    .attr('x',          (-iconWidth / 2) + 'px')
    .attr('y',          (-iconWidth + 5) + 'px')
    .attr('width',      iconWidth)
    .attr('height',     iconWidth);

  // y-axis image ticks
  svg.select('.y-axis').selectAll('text').remove();
  svg.selectAll('.y-axis .tick').data(victimModes)
    .append('image')
    .attr('xlink:href', d => '/images/' + getModeImage(parseInt(d)))
    .attr('x', (-iconWidth / 2 - 10) + 'px')
    .attr('y', (-iconWidth / 2) + 'px')
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
  let tooltip = d3.select('#' + divID)
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
      .select('.data-circle')
      .style('stroke', '#000000')
      .transition()
      .duration(500)
      .attr('r', d => d.value > 0? 1.4 * rScale(d.value) : 1);
  };

  let mousemove = function(data) {
    const pointMouse = d3.mouse(this);
    const svgWidth = d3.select('#' + divID).node().getBoundingClientRect().width;
    const scaleFactor = svgWidth / widthContainer;
    let xMouse = xScale(data.partnerMode) + pointMouse[0];
    let yMouse = yScale(data.victimMode) + pointMouse[1];
    xMouse = xMouse * scaleFactor + 25;
    yMouse = yMouse * scaleFactor - 25;

    const modeText = data.partnerMode === -1? 'Eenzijdig ongeluk' : 'Tegenpartij: ' + transportationModeText(data.partnerMode);
    let html = modeText + '<br>';
    html += `${data.value} ${transportationModeText(data.victimMode)}`;
    html += data.value === 1? ' dode' : ' doden';
    tooltip.html(html)
      .style('left', xMouse + 'px')
      .style('top',  yMouse + 'px');
  };

  let mouseleave = function(data) {
    tooltip.style('display', 'none');

    d3.select(this)
      .style('cursor', 'default')
      .select('.data-circle')
      .style('stroke', 'none')
      .transition()
      .duration(500)
      .attr('r', d => d.value > 0? rScale(d.value) : 1);
  };

  const iOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

  // Data item
  var dataElement = svg.selectAll()
    .data(data)
    .enter()
    .append("a")
    .attr("xlink:href", d => {
      let url = '/?search=&persons=' + d.victimMode + 'd';
      if (d.victimMode === d.partnerMode) url += 'r'; // Restricted
      else if (d.partnerMode === -1) url += 'u'; // Unilateral
      else url += `,${d.partnerMode}`;

      if (filter.child) url += '&child=1';
      if (filter.period) {
        url += '&period=' + filter.period;
        if (filter.dateFrom) url += '&date_from=' + filter.dateFrom;
        if (filter.dateTo)   url += '&date_to=' + filter.dateTo;
      }
      return url;})
    .append("g")
    .attr("transform", d => `translate(${xScale(d.partnerMode) + xScale.bandwidth()/2}, ${yScale(d.victimMode) + yScale.bandwidth()/2})`)
    .on('mouseover',  mouseover)
    .on('mousemove',  iOS? null : mousemove)
    .on('mouseleave', mouseleave);

  // Data circles
  dataElement.append('circle')
    .attr('class', 'data-circle')
    .attr('r',        0)
    // .style('fill',    d => d.value > 0? '#df3b34' : '#999999')
    .style('fill',    d => d.value > 0? colorScale(d.value) : '#999999')
    .style('opacity', 0.8)
    .transition()
    .ease(d3.easeSin)
    .duration(2000)
    .attr('r', d => d.value > 0? rScale(d.value) : 1);

  // Data text
  dataElement.filter(d => d.value > 0)
    .append("text")
    .attr('class', 'data-text')
    .attr('dx', 0)
    .attr('text-anchor', 'middle')
    .attr('alignment-baseline', 'central')
    .attr('dominant-baseline', 'middle')
    // Smaller font if it does not fit the circle
    .style('font-size', d => rScale(d.value) > 6? '10px' : '8px')
    .style('fill', '#ffffff')
    .transition()
    .delay(2000)
    .text(d => d.value);

}
