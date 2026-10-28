"""
Converts the Lexis Nexis csv of the students (analysis_corpus_2024.csv) to a file with one article per line (json),
for install/import_lexisnexis_csv.php.

  python lexisnexis_csv_to_jsonl.py analysis_corpus_2024.csv articles.jsonl
"""
import ast, csv, json, re, sys

csv.field_size_limit(10**9)

# Estimated accuracy of the place in metres, from the result type of the geocoder (HERE)
ACCURACY = {'houseNumber': 30, 'intersection': 50, 'street': 150, 'place': 300, 'locality': 3000, 'administrativeArea': 20000}


def parse_modes(text):
  # The persons are saved as python text with numpy arrays: array([...], dtype=object)
  # The lines are separated by a backslash and an n, and the items have no comma between them
  # Text with an apostrophe is between double quotes, which the csv has escaped with a backslash
  text = text.strip().strip('"').replace('\\n', ' ').replace('\\"', '"')
  text = re.sub(r'array\(', '(', text)
  text = re.sub(r',\s*dtype=object\)', ')', text)
  text = re.sub(r'\}\s+\{', '}, {', text)
  return ast.literal_eval(text)


def first_outlet(text):
  match = re.search(r"'([^']+)'", text)
  return match.group(1) if match else ''


def main(path_in, path_out):
  count = failed = 0
  with open(path_in, encoding='utf-8', newline='') as f_in, open(path_out, 'w', encoding='utf-8') as f_out:
    for row in csv.DictReader(f_in):
      try:
        modes = parse_modes(row['transportation_modes'])
      except Exception as e:
        failed += 1
        print('Persons not readable, article', row['article_id'], e, file=sys.stderr)
        modes = []

      persons = []
      for group, mode in enumerate(modes, start=1):
        for human in mode['humans']:
          persons.append({'groupid': group, 'transportationmode': mode['transportation_mode'], 'health': human.get('health', 'unknown'),
                          'child': bool(human.get('child')), 'underinfluence': bool(human.get('intoxicated')), 'hitrun': bool(human.get('fled_scene'))})

      has_place = row['latitude'] != '' and row['longitude'] != ''
      article = {
        'source': 'lexisnexis',
        'externalid': row['article_id'],
        'title': row['title'],
        'alltext': row['body'],
        'date': row['pub_date'] + 'T00:00:00',
        'sitename': first_outlet(row['outlet']),
        'url': '',
        'urlimage': '',
        'event': row['crash_event_id'],
        'crash': {
          'date': row['crash_date'],
          'countryid': row['country_code'],
          'latitude': float(row['latitude']) if has_place else None,
          'longitude': float(row['longitude']) if has_place else None,
          'locationaccuracy': ACCURACY.get(row['here_result_type']) if has_place else None,
          'locationdescription': row['location_description'],
          'unilateral': len(modes) == 1 and len(persons) == 1,
          'pet': row['animals_mentioned'] == 'True',
          'trafficjam': row['traffic_congestion'] == 'True',
          'persons': persons,
        },
      }
      f_out.write(json.dumps(article, ensure_ascii=False) + '\n')
      count += 1

  print(count, 'articles written,', failed, 'without readable persons')


if __name__ == '__main__':
  if len(sys.argv) != 3:
    sys.exit(__doc__)
  main(sys.argv[1], sys.argv[2])
