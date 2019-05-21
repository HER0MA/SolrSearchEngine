import org.apache.log4j.PropertyConfigurator;

import java.io.FileNotFoundException;
import java.io.IOException;

public class Main {
    public static void main(String[] args) {
        String log4jConfPath = "./log4j.properties";
        PropertyConfigurator.configure(log4jConfPath);
        CreateEdgeList createEdgeList = new CreateEdgeList();
        try {
            createEdgeList.initMaps();
            createEdgeList.createEdgeList();
        } catch (FileNotFoundException e) {
            e.printStackTrace();
        } catch (IOException e) {
            e.printStackTrace();
        }
    }
}
