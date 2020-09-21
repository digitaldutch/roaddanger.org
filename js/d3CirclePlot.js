

function CrashPartnerGraph(divID, data, optionsUser=[], filter=null) {
  let widthContainer   = 700;
  let heightContainer  = 500;
  let xLabel           = '';
  let yLabel           = '';
  const margin         = {top: 60, left: 70, right: 10, bottom: 10};
  const iconWidth      = 30;

  if (optionsUser) {
    if (optionsUser.width)  widthContainer  = optionsUser.width;
    if (optionsUser.height) heightContainer = optionsUser.height;
    if (optionsUser.xLabel) xLabel          = optionsUser.xLabel;
    if (optionsUser.yLabel) yLabel          = optionsUser.yLabel;
  }

  // Clear current chart
  d3.select('#' + divID).selectAll('svg').remove();

  // Create new graph
  const svg = d3.select('#' + divID)
    .append('svg')
    .attr('id', 'svgGraph')
    // Make SVG responsive by preserving aspect ratio,  adding viewbox and omitting width and height attributes
    .attr("preserveAspectRatio", "xMinYMin meet")
    .attr("viewBox", `0 0 ${widthContainer} ${heightContainer}`)

  const victimModes  = d3.map(data, d => d.victimMode).keys();
  const partnerModes = d3.map(data, d => d.partnerMode).keys();
  const valueExtent  = d3.extent(data.map(d => d.value));

  const victimTotals  = [];
  const partnerTotals = [];
  victimModes.forEach(v => victimTotals[v] = 0);
  partnerModes.forEach(p => partnerTotals[p] = 0);

  data.forEach(d => {
    if (d.value) {
      victimTotals[d.victimMode]   += d.value;
      partnerTotals[d.partnerMode] += d.value;
    }
  });

  const xScale = d3.scaleBand()
    .domain(partnerModes)
    .range([margin.left, widthContainer - margin.right]);

  const yScale = d3.scaleBand()
    .domain(victimModes.reverse())
    .range([heightContainer - margin.bottom, margin.top]);

  const colorScale = d3.scaleLinear()
    .domain(valueExtent)
    .range(["#f5977b", "#ff0000"]);

  // x-axis
  svg.append('g')
    .attr('class', 'x-axis')
    .style('font-size', 12)
    .attr('transform', `translate(0, ${margin.top - 15})`)
    .call(d3.axisTop(xScale).tickSize(0))
    .select('.domain').remove();

  // y-axis
  svg.append('g')
    .attr('class', 'y-axis')
    .style('font-size', 12)
    .attr('transform', `translate(${margin.left}, 0)`)
    .call(d3.axisLeft(yScale).tickSize(0))
    .select('.domain').remove();

  // x-axis
  // Remove mode texts
  svg.select('.x-axis').selectAll('text').remove();

  // x-axis
  const x_tick = svg.selectAll('.x-axis .tick').data(partnerModes);

  // x-axis icons
  x_tick
    .append('image')
    .attr('xlink:href', d => '/images/' + getModeImage(parseInt(d)))
    .attr('x',          (-iconWidth / 2) + 'px')
    .attr('y',          (-iconWidth + 5) + 'px')
    .attr('width',      iconWidth)
    .attr('height',     iconWidth);

  // x-axis total texts
  x_tick
    .append("text")
    .attr('class', 'data-text')
    .attr('dx', 0)
    .attr('dy', '13px')
    .style('font-size', '10px')
    .style('fill', '#000000')
    .text(d => d3.format('.2~s')(partnerTotals[parseInt(d)]));

  // y-axis
  // Remove mode texts
  svg.select('.y-axis').selectAll('text').remove();

  const y_tick = svg.selectAll('.y-axis .tick').data(victimModes);

  // y-axis image ticks
  y_tick
    .append('image')
    .attr('xlink:href', d => '/images/' + getModeImage(parseInt(d)))
    .attr('x', (-iconWidth / 2 - 35) + 'px')
    .attr('y', (-iconWidth / 2) + 'px')
    .attr('width', iconWidth)
    .attr('height',iconWidth);

  // y-axis total texts
  y_tick
    .append("text")
    .attr('class', 'data-text')
    .attr('dx', '8px')
    .attr('dy', '3px')
    .style('font-size', '10px')
    .style('fill', '#000000')
    .text(d => d3.format('.2~s')(victimTotals[parseInt(d)]));


  // Square root scale, because value = area πr2
  const rScale = d3.scaleSqrt()
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
  const tooltip = d3.select('#' + divID)
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

  const mouseoverItem = function() {
    tooltip.style('display', 'flex');

    d3.select(this)
      .style('cursor', data => data.value===0? 'default' : 'pointer')
      .select('.data-circle')
      .style('stroke', '#000000')
      .transition()
      .duration(500)
      .attr('r', d => d.value > 0? 1.5 * rScale(d.value) : 1);

    d3.select(this)
      .select('.data-text')
      .transition()
      .duration(500)
      .style('font-size', '15px');
  };

  const mousemoveItem = function(data) {
    const pointMouse  = d3.mouse(this);
    const svgWidth    = d3.select('#' + divID).node().getBoundingClientRect().width;
    const scaleFactor = svgWidth / widthContainer;

    let xMouse = xScale(data.partnerMode) + pointMouse[0];
    let yMouse = yScale(data.victimMode) + pointMouse[1];
    xMouse = xMouse * scaleFactor + 25;
    yMouse = yMouse * scaleFactor - 25;

    const modeText = data.partnerMode === -1? translate('One-sided_crash') : translate('Counterparty') + ': ' + transportationModeText(data.partnerMode);

    let html = modeText + '<br>';
    html += `${data.value}&nbsp${transportationModeText(data.victimMode)}&nbsp`;
    html += data.value === 1? translate('dead_(single)') : translate('dead_(multiple)');
    if (filter.healthInjured) html += '/' + translate('injured');

    tooltip.html(html)
      .style('left', xMouse + 'px')
      .style('top',  yMouse + 'px');
  };

  const mouseleaveItem = function() {
    tooltip.style('display', 'none');

    d3.select(this)
      .style('cursor', 'default')
      .select('.data-circle')
      .style('stroke', 'none')
      .transition()
      .duration(500)
      .attr('r', d => d.value > 0? rScale(d.value) : 1);

    d3.select(this)
      .select('.data-text')
      .transition()
      .duration(500)
      .style('font-size', '10px');
  };

  const iOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

  // Data item
  const dataElement = svg.selectAll()
    .data(data)
    .enter()
    .append("a")
    .attr("xlink:href", d => {
      let url = '/?search=&persons=' + d.victimMode + 'd';
      if (filter.healthInjured) url += 'i';
      if      (d.victimMode === d.partnerMode) url += 'r'; // Restricted
      else if (d.partnerMode === -1) url += 'u'; // Unilateral
      else url += `,${d.partnerMode}`;

      if (filter.child)   url += '&child=1';
      if (filter.country) url += '&country=' + filter.country;
      if (filter.period) {
        url += '&period=' + filter.period;
        if (filter.dateFrom) url += '&date_from=' + filter.dateFrom;
        if (filter.dateTo)   url += '&date_to=' + filter.dateTo;
      }
      return url;})
    .append("g")
    .attr("transform", d => `translate(${xScale(d.partnerMode) + xScale.bandwidth()/2}, ${yScale(d.victimMode) + yScale.bandwidth()/2})`)
    .on('mouseover',  mouseoverItem)
    .on('mousemove',  iOS? null : mousemoveItem)
    .on('mouseleave', mouseleaveItem);

  // Data circles
  dataElement.append('circle')
    .attr('class', 'data-circle')
    .attr('r',        0)
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
    .attr('text-anchor', 'middle')
    .attr('alignment-baseline', 'central')
    .attr('dominant-baseline', 'middle')
    // Smaller font if it does not fit the circle
    .style('font-size', '10px')
    .style('fill', '#000000')
    .transition()
    .delay(2000)
    .text(d => d3.format('.2~s')(d.value));

}
