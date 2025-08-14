{\rtf1\ansi\ansicpg1252\cocoartf2822
\cocoatextscaling0\cocoaplatform0{\fonttbl\f0\fswiss\fcharset0 Helvetica;}
{\colortbl;\red255\green255\blue255;}
{\*\expandedcolortbl;;}
\margl1440\margr1440\vieww11520\viewh8400\viewkind0
\pard\tx720\tx1440\tx2160\tx2880\tx3600\tx4320\tx5040\tx5760\tx6480\tx7200\tx7920\tx8640\pardirnatural\partightenfactor0

\f0\fs24 \cf0 # Trip Processor (PHP 8)\
\
## Overview\
This script reads a `points.csv` file containing GPS coordinates and timestamps, cleans and processes the data into sequential trips, and outputs a GeoJSON file for mapping.\
\
## Features\
- **Cleans Data**: Discards invalid coordinates and unparseable timestamps, logging them to `rejects.log`.\
- **Sorts by Time**: Orders all valid points chronologically.\
- **Trip Splitting**:\
  - New trip if time gap > 25 minutes OR straight-line jump > 2 km.\
- **Statistics** (per trip):\
  - Total distance (km)\
  - Duration (minutes)\
  - Average speed (km/h)\
  - Maximum segment speed (km/h)\
- **GeoJSON Output**: Each trip as a `LineString`, each with a different color.\
\
## Requirements\
- PHP 8.0 or later\
- No external libraries\
- `points.csv` in the same directory\
\
## CSV Format\
The CSV must have headers (case-insensitive):\
- `lat` or `latitude`\
- `lon`, `lng`, or `longitude`\
- `timestamp`, `time`, `datetime`, or `date`\
\
Example:\
```csv\
lat,lon,timestamp\
14.5995,120.9842,2025-08-14T08:30:00Z\
14.5997,120.9845,2025-08-14T08:45:00Z}