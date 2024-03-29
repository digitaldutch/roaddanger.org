
function CrashPartnerGraph(divID, data, optionsUser=[], filter=null) {
  let widthContainer = 800;
  let heightContainer = 600;

  let xLabel = '';
  let yLabel = '';

  const margin = {top: 60, left: 70, right: 10, bottom: 10};
  const iconWidth = 30;
  const fontSize = '12px';

  if (optionsUser) {
    if (optionsUser.width) widthContainer = optionsUser.width;
    if (optionsUser.height) heightContainer = optionsUser.height;
    if (optionsUser.xLabel) xLabel = optionsUser.xLabel;
    if (optionsUser.yLabel) yLabel = optionsUser.yLabel;
  }

  // Clear current chart
  d3.select('#' + divID).selectAll('*').remove();

  // Create new graph
  const svg = d3.select('#' + divID)
    .append('svg')
    .attr('id', 'svgGraph')
    // Make SVG responsive by preserving aspect ratio,  adding viewbox and omitting width and height attributes
    .attr("preserveAspectRatio", "xMinYMin meet")
    .attr("viewBox", `0 0 ${widthContainer} ${heightContainer}`)

  const victimModes = [];
  const partnerModes = [];
  data.forEach(d => {if (!victimModes.includes(d.victimMode)) victimModes.push(d.victimMode)});
  data.forEach(d => {if (!partnerModes.includes(d.partnerMode)) partnerModes.push(d.partnerMode)});
  const valueExtent = d3.extent(data, d => d.value);

  const victimTotals = [];
  const partnerTotals = [];
  victimModes.forEach(v => victimTotals[v] = 0);
  partnerModes.forEach(p => partnerTotals[p] = 0);

  data.forEach(d => {
    if (d.value) {
      victimTotals[d.victimMode] += d.value;
      partnerTotals[d.partnerMode] += d.value;
    }
  });

  const xScale = d3.scaleBand()
    .domain(partnerModes)
    .range([margin.left, widthContainer - margin.right]);

  const yScale = d3.scaleBand()
    .domain(victimModes.reverse())
    .range([heightContainer - margin.bottom, margin.top])
    .paddingInner(0.3);

  const colorScale = d3.scaleLinear()
    .domain(valueExtent)
    .range(["#f5977b", "#ff0000"]);

  // Radius scale is a square root scale, because value = area πr2
  const rScale = d3.scaleSqrt()
    .domain(valueExtent)
    .range([1, 0.8 * yScale.bandwidth()]);

  // x-axis
  svg.append('g')
    .attr('class', 'x-axis')
    .style('font-size', fontSize)
    .attr('transform', `translate(0, ${margin.top - 15})`)
    .call(d3.axisTop(xScale).tickSize(0))
    .select('.domain').remove();

  // y-axis
  svg.append('g')
    .attr('class', 'y-axis')
    .style('font-size', fontSize)
    .attr('transform', `translate(${margin.left}, 0)`)
    .call(d3.axisLeft(yScale).tickSize(0))
    .select('.domain').remove();

  // x-axis
  // Remove mode integer texts. We replace these with icons
  svg.select('.x-axis').selectAll('text').remove();

  // x-axis
  const x_tick = svg.selectAll('.x-axis .tick').data(partnerModes);

  // x-axis icons
  x_tick
    .append('image')
    .attr('xlink:href', d => getTransportationModeIcon(parseInt(d)))
    .attr('x', (-iconWidth / 2) + 'px')
    .attr('y', (-iconWidth + 5) + 'px')
    .attr('width', iconWidth)
    .attr('height', iconWidth);

  // x-axis total texts
  x_tick
    .append("text")
    .attr('dx', 0)
    .attr('dy', '13px')
    .style('font-size', fontSize)
    .style('fill', '#000000')
    .text(d => d3.format('.3~s')(partnerTotals[parseInt(d)]));

  // y-axis
  // Remove integer mode texts. We replace these with icons
  svg.select('.y-axis').selectAll('text').remove();

  const y_tick = svg.selectAll('.y-axis .tick').data(victimModes);

  // y-axis image ticks
  y_tick
    .append('image')
    .attr('xlink:href', d => getTransportationModeIcon(parseInt(d)))
    .attr('x', (-iconWidth / 2 - 35) + 'px')
    .attr('y', (-iconWidth / 2) + 'px')
    .attr('width', iconWidth)
    .attr('height',iconWidth);

  // y-axis total texts
  y_tick
    .append("text")
    .attr('dx', '8px')
    .attr('dy', '3px')
    .style('font-size', fontSize)
    .style('fill', '#000000')
    .text(d => d3.format('.3~s')(victimTotals[parseInt(d)]));

  if (xLabel) {
    svg.append('text')
      .attr('transform', `translate(${widthContainer / 2}, 30)`)
      .attr('dy', '-1em')
      .style('text-anchor', 'middle')
      .text(xLabel);
  }

  if (yLabel) {
    svg.append('text')
      .attr('transform', 'rotate(-90)')
      .attr('x', - heightContainer / 2)
      .attr('y', 0)
      .attr('dy', '1em')
      .style('text-anchor', 'middle')
      .text(yLabel);
  }

  // create the tooltip element
  const tooltip = d3.select('#' + divID)
    .append('div')
    .attr('id', 'tooltip' + divID)
    .style('display', 'none')
    .style('position', 'absolute')
    .style('color', 'black')
    .style('background-color', 'white')
    .style('font-size', fontSize)
    .style('border', 'solid 1px #666')
    .style('border-radius', '5px')
    .style('padding', '2px 5px');

  function getTransportationModeIcon(transportationModeValue){
    return transportationModeValue === -1? '/images/unilateral.svg' : '/images/transportation_modes/' + transportationImageFileName(transportationModeValue);
  }

  const mouseoverItem = (event, data) => {
    const modeText = data.partnerMode === -1? translate('One-sided_crash') : translate('Counterparty') + ': ' + transportationModeText(data.partnerMode);

    let html = modeText + '<br>';
    html += `${d3.format('.3~s')(data.value)}&nbsp${transportationModeText(data.victimMode)}&nbsp`;
    html += data.value === 1? translate('dead_(single)') : translate('dead_(multiple)');
    if (filter.healthInjured) html += '/' + translate('injured');

    tooltip.html(html).style('display', 'flex');

    d3.select(event.currentTarget)
      .style('cursor', data => data.value === 0? 'default' : 'pointer')
      .select('.data-circle')
      .style('stroke', '#000000')
      .transition()
      .duration(500)
      .attr('r', data => data.value > 0? 1.5 * rScale(data.value) : 1);

    d3.select(event.currentTarget)
      .select('.data-text')
      .transition()
      .duration(500)
      .style('font-size', '15px');
  };

  const mousemoveItem = (event, data) => {
    const [pointer_x, pointer_y] = d3.pointer(event);
    const svgWidth = d3.select('#' + divID).node().getBoundingClientRect().width;
    const scaleFactor = svgWidth / widthContainer;

    let xMouse = xScale(data.partnerMode) + pointer_x;
    let yMouse = yScale(data.victimMode) + pointer_y;

    xMouse = xMouse * scaleFactor + 25;
    yMouse = yMouse * scaleFactor - 25;

    tooltip
      .style('left', xMouse + 'px')
      .style('top', yMouse + 'px');
  };

  const mouseleaveItem = (event, data) => {
    tooltip.style('display', 'none');

    d3.select(event.currentTarget)
      .style('cursor', 'default')
      .select('.data-circle')
      .style('stroke', 'none')
      .transition()
      .duration(500)
      .attr('r', data => data.value > 0? rScale(data.value) : 1);

    d3.select(event.currentTarget)
      .select('.data-text')
      .transition()
      .duration(500)
      .style('font-size', fontSize);
  };

  // Data item
  const dataElement = svg.selectAll()
    .data(data)
    .enter()
    .append("a")
    .attr("xlink:href", d => {
      let url = '/?search=';
      if (filter.text) url += encodeURIComponent(filter.text);
      url += '&persons=' + d.victimMode + 'd';

      if (filter.healthInjured) url += 'i';
      if (d.victimMode === d.partnerMode) url += 'r'; // Restricted
      else if (d.partnerMode === -1) url += 'u'; // Unilateral
      else url += `,${d.partnerMode}`;

      if (filter.child) url += '&child=1';
      if (filter.country) url += '&country=' + filter.country;
      if (filter.period) {
        url += '&period=' + filter.period;
        if (filter.dateFrom) url += '&date_from=' + filter.dateFrom;
        if (filter.dateTo) url += '&date_to=' + filter.dateTo;
      }
      return url;})
    .append("g")
    .attr("transform", d => `translate(${xScale(d.partnerMode) + xScale.bandwidth()/2}, ${yScale(d.victimMode) + yScale.bandwidth()/2})`)
    .on('mouseover', mouseoverItem)
    .on('mousemove', mousemoveItem)
    .on('mouseleave', mouseleaveItem);

  // Red data circles
  dataElement.append('circle')
    .attr('class', 'data-circle')
    .attr('r', d => d.value > 0? rScale(d.value) : 1)
    .style('fill', d => d.value > 0? colorScale(d.value) : '#999999')
    .style('opacity', 0.8);

  // Data text
  dataElement
    .filter(d => d.value > 0)
    .append("text")
    .attr('class', 'data-text')
    .attr('text-anchor', 'middle')
    .attr('alignment-baseline', 'central')
    .attr('dominant-baseline', 'middle')
    // Smaller font if it does not fit the circle
    .style('font-size', fontSize)
    .style('fill', '#000000')
    .text(d => d3.format('.3~s')(d.value));

}
