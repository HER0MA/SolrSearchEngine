import org.apache.log4j.Logger;
import org.jsoup.Jsoup;
import org.jsoup.nodes.Document;
import org.jsoup.nodes.Element;
import org.jsoup.select.Elements;

import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileWriter;
import java.io.IOException;
import java.util.HashMap;
import java.util.HashSet;
import java.util.Scanner;
import java.util.Set;

public class CreateEdgeList {
    private final static String dirPath = "~/solr/crawl_data";
    private final static String edgeListPath = "./Edgelist.txt";
    private final static String mapFilePath = "./map.csv";
    private HashMap<String, String> fileUrlMap;
    private HashMap<String, String> urlFileMap;

    public CreateEdgeList() {
        this.fileUrlMap = new HashMap<String, String>();
        this.urlFileMap = new HashMap<String, String>();
    }

    public void initMaps() throws FileNotFoundException {
        Scanner sc = new Scanner(new File(mapFilePath));
        while (sc.hasNextLine()) {
            String[] nameAndUrl = sc.nextLine().trim().split(",");
            fileUrlMap.put(nameAndUrl[0].trim(), nameAndUrl[1].trim());
            urlFileMap.put(nameAndUrl[1].trim(), nameAndUrl[0].trim());
        }
    }

    public void createEdgeList() throws IOException {
        File dir = new File(dirPath);
        Set<String> edges = new HashSet<String>();
        Logger logger = Logger.getLogger(CreateEdgeList.class);
        int count = 0;
        int size = dir.listFiles().length;
        for (File file : dir.listFiles()) {
            if (".DS_Store".equals(file.getName())) {
                continue;
            }
            count++;
            Document doc = Jsoup.parse(file, "UTF-8", fileUrlMap.get(file.getName()));
            Elements links = doc.select("a[href]");
            Elements media = doc.select("[src]");
            Elements imports = doc.select("link[href]");

            for (Element link : links) {
                String url = link.attr("abs:href").trim();
                if (urlFileMap.containsKey(url)) {
                    edges.add(file.getName() + " " + urlFileMap.get(url));
                    logger.debug("[" + (count * 1.0 / size * 100) + "%] "  + "Add edge " + file.getName() + " " + urlFileMap.get(url));
                }
            }
            for (Element medium : media) {
                String url = medium.attr("abs:href").trim();
                if (urlFileMap.containsKey(url)) {
                    edges.add(file.getName() + " " + urlFileMap.get(url));
                    logger.debug("[" + (count * 1.0 / size * 100) + "%] "  + "Add edge " + file.getName() + " " + urlFileMap.get(url));
                }
            }
            for (Element impo : imports) {
                String url = impo.attr("abs:href").trim();
                if (urlFileMap.containsKey(url)) {
                    edges.add(file.getName() + " " + urlFileMap.get(url));
                    logger.debug("[" + (count * 1.0 / size * 100) + "%] "  + "Add edge " + file.getName() + " " + urlFileMap.get(url));
                }
            }
        }

        FileWriter writer = new FileWriter(new File(edgeListPath));
        logger.debug("Writting results to file...");
        for (String e : edges) {
            writer.write(e + "\n");
        }
        logger.debug("Done!");
        logger.debug("urlFileMap size:" + urlFileMap.size());
        logger.debug("urlFileMap size：" + fileUrlMap.size());
        writer.flush();
        writer.close();
    }
}